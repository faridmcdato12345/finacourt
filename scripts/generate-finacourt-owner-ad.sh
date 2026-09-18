#!/usr/bin/env bash

set -Eeuo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
demo_directory="${project_root}/output/demo-video"
work_directory="${demo_directory}/owner-ad-work"
asset_directory="${project_root}/tools/demo-video/owner-ad"
container_name="finacourt-owner-ad-render"
browser_image="${DEMO_VIDEO_BROWSER_IMAGE:-selenium/standalone-chromium:latest}"

required_scenes=(
    scene-owner-bookings.trimmed.mp4
    scene-owner-analytics.trimmed.mp4
    scene-owner-links.trimmed.mp4
)

for scene in "${required_scenes[@]}"; do
    if [[ ! -s "${demo_directory}/${scene}" ]]; then
        echo "Missing reusable owner-demo scene: ${demo_directory}/${scene}" >&2
        echo "Generate the existing owner demo first with DEMO_VIDEO_VARIANT=owner ./scripts/generate-finacourt-demo.sh" >&2
        exit 1
    fi
done

if [[ ! -s "${project_root}/public/icons/finacourt-logo.png" ]]; then
    echo "Missing FinACourt logo: public/icons/finacourt-logo.png" >&2
    exit 1
fi

mkdir -p "${work_directory}"
chmod 0777 "${demo_directory}" "${work_directory}"
cp "${asset_directory}/finacourt-owner-ad-25s.srt" "${demo_directory}/finacourt-owner-ad-25s.srt"
cp "${asset_directory}/finacourt-owner-ad-storyboard.md" "${demo_directory}/finacourt-owner-ad-storyboard.md"

cleanup() {
    docker rm -f "${container_name}" >/dev/null 2>&1 || true
}

trap cleanup EXIT
cleanup

docker run -d \
    --name "${container_name}" \
    -e SE_START_XVFB=true \
    -v "${project_root}/output:/output" \
    -v "${project_root}/tools/demo-video:/work:ro" \
    -v "${project_root}/public/icons:/icons:ro" \
    "${browser_image}" >/dev/null

for _attempt in $(seq 1 60); do
    if docker exec "${container_name}" sh -lc 'command -v ffmpeg >/dev/null && command -v chromium >/dev/null'; then
        break
    fi
    sleep 1
done

if ! docker exec "${container_name}" sh -lc 'command -v ffmpeg >/dev/null && command -v chromium >/dev/null'; then
    echo "The render container did not provide FFmpeg and Chromium." >&2
    exit 1
fi

render_overlay() {
    local scene="$1"

    docker exec "${container_name}" chromium \
        --headless \
        --no-sandbox \
        --disable-gpu \
        --hide-scrollbars \
        --allow-file-access-from-files \
        --force-device-scale-factor=1 \
        --window-size=1080,2008 \
        --default-background-color=00000000 \
        --screenshot="/output/demo-video/owner-ad-work/${scene}.capture.png" \
        "file:///work/owner-ad/overlay.html?scene=${scene}" >/dev/null 2>&1

    # Chromium reserves 88 px for browser UI even in headless mode. Capture a
    # taller window, then retain the exact 1080x1920 content viewport.
    docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
        -i "/output/demo-video/owner-ad-work/${scene}.capture.png" \
        -vf 'crop=1080:1920:0:0' \
        "/output/demo-video/owner-ad-work/${scene}.png"
}

for scene in clean hook bookings analytics links cta; do
    render_overlay "${scene}"
done

encode_ui_base() {
    local source_file="$1"
    local output_file="$2"
    local crop_x="$3"
    local trim_expression="$4"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
        -i "/output/demo-video/${source_file}" \
        -loop 1 -framerate 30 -i /output/demo-video/owner-ad-work/clean.png \
        -filter_complex "[0:v]${trim_expression},crop=608:1080:${crop_x}:0,scale=1080:-2:flags=lanczos,pad=1080:1920:0:(oh-ih)/2,setpts=PTS-STARTPTS,fps=30[ui];[1:v]format=rgba[mask];[ui][mask]overlay=0:0:eof_action=repeat:shortest=1,format=yuv420p[v]" \
        -map '[v]' -an -t 5 \
        -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
        "/output/demo-video/owner-ad-work/${output_file}"
}

overlay_caption() {
    local base_file="$1"
    local overlay_file="$2"
    local output_file="$3"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
        -i "/output/demo-video/owner-ad-work/${base_file}" \
        -loop 1 -framerate 30 -i "/output/demo-video/owner-ad-work/${overlay_file}" \
        -filter_complex '[1:v]format=rgba[copy];[0:v][copy]overlay=0:0:eof_action=repeat:shortest=1,format=yuv420p[v]' \
        -map '[v]' -an -t 5 \
        -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
        "/output/demo-video/owner-ad-work/${output_file}"
}

docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
    -i /output/demo-video/scene-owner-bookings.trimmed.mp4 \
    -loop 1 -framerate 30 -i /output/demo-video/owner-ad-work/hook.png \
    -filter_complex "[0:v]trim=start=0.4:duration=3,crop=608:1080:290:0,scale=1080:-2:flags=lanczos,pad=1080:1920:0:(oh-ih)/2,gblur=sigma=18,eq=brightness=-0.36:saturation=0.55,setpts=PTS-STARTPTS,fps=30[bg];[1:v]format=rgba[copy];[bg][copy]overlay=0:0:eof_action=repeat:shortest=1,format=yuv420p[v]" \
    -map '[v]' -an -t 3 \
    -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
    /output/demo-video/owner-ad-work/scene-hook.mp4

encode_ui_base \
    scene-owner-bookings.trimmed.mp4 \
    scene-bookings-raw.mp4 \
    '290+8*t' \
    'trim=start=0.4:duration=5'
encode_ui_base \
    scene-owner-analytics.trimmed.mp4 \
    scene-analytics-raw.mp4 \
    '340+7*t' \
    'trim=start=0.4:duration=5'
encode_ui_base \
    scene-owner-links.trimmed.mp4 \
    scene-links-raw.mp4 \
    '340+7*t' \
    'trim=start=0.2:end=4.3,setpts=(PTS-STARTPTS)/0.82'

overlay_caption scene-bookings-raw.mp4 bookings.png scene-bookings.mp4
overlay_caption scene-analytics-raw.mp4 analytics.png scene-analytics.mp4
overlay_caption scene-links-raw.mp4 links.png scene-links.mp4

docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
    -f lavfi -i color=c=0x03251d:s=1080x1920:r=30:d=6 \
    -loop 1 -framerate 30 -i /output/demo-video/owner-ad-work/cta.png \
    -filter_complex '[1:v]format=rgba[cta];[0:v][cta]overlay=0:0:eof_action=repeat:shortest=1,format=yuv420p[v]' \
    -map '[v]' -an -t 6 -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
    /output/demo-video/owner-ad-work/scene-cta.mp4

final_list="${work_directory}/final-scenes.txt"
raw_list="${work_directory}/raw-scenes.txt"
printf '%s\n' \
    "file '/output/demo-video/owner-ad-work/scene-hook.mp4'" \
    "file '/output/demo-video/owner-ad-work/scene-bookings.mp4'" \
    "file '/output/demo-video/owner-ad-work/scene-analytics.mp4'" \
    "file '/output/demo-video/owner-ad-work/scene-links.mp4'" \
    "file '/output/demo-video/owner-ad-work/scene-cta.mp4'" \
    >"${final_list}"
printf '%s\n' \
    "file '/output/demo-video/owner-ad-work/scene-hook.mp4'" \
    "file '/output/demo-video/owner-ad-work/scene-bookings-raw.mp4'" \
    "file '/output/demo-video/owner-ad-work/scene-analytics-raw.mp4'" \
    "file '/output/demo-video/owner-ad-work/scene-links-raw.mp4'" \
    "file '/output/demo-video/owner-ad-work/scene-cta.mp4'" \
    >"${raw_list}"

encode_concat() {
    local list_file="$1"
    local output_file="$2"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
        -f concat -safe 0 -i "/output/demo-video/owner-ad-work/${list_file}" \
        -vf 'fps=30,scale=1080:1920:flags=lanczos,format=yuv420p' \
        -an -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
        -movflags +faststart \
        -metadata title='FinACourt owner ad' \
        "/output/demo-video/${output_file}"
}

encode_concat final-scenes.txt finacourt-owner-ad-25s.mp4
encode_concat raw-scenes.txt finacourt-owner-ad-25s-raw.mp4

docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
    -i /output/demo-video/finacourt-owner-ad-25s.mp4 \
    -frames:v 1 /output/demo-video/finacourt-owner-ad-cover.png

metadata="$(docker exec "${container_name}" ffmpeg -hide_banner -i /output/demo-video/finacourt-owner-ad-25s.mp4 -f null - 2>&1)"

if [[ "${metadata}" =~ Duration:\ ([0-9]{2}):([0-9]{2}):([0-9]+\.[0-9]+) ]]; then
    duration="$(awk -v hours="${BASH_REMATCH[1]}" -v minutes="${BASH_REMATCH[2]}" -v seconds="${BASH_REMATCH[3]}" 'BEGIN { printf "%.3f", (hours * 3600) + (minutes * 60) + seconds }')"
else
    echo "FFmpeg could not read the final owner-ad duration." >&2
    exit 1
fi

if ! awk -v duration="${duration}" 'BEGIN { exit !(duration >= 23 && duration <= 25) }'; then
    echo "Final duration ${duration}s is outside the required 23–25 second range." >&2
    exit 1
fi

for expected in 'Video: h264' 'yuv420p' '1080x1920' '30 fps'; do
    if [[ "${metadata}" != *"${expected}"* ]]; then
        echo "Final owner-ad validation failed: expected ${expected}." >&2
        echo "${metadata}" >&2
        exit 1
    fi
done

find "${work_directory}" -depth -delete

echo "Validated: H.264, yuv420p, 1080x1920, 30 fps, ${duration}s."
echo "Created ${demo_directory}/finacourt-owner-ad-25s.mp4"
