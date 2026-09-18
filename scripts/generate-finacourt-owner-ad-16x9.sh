#!/usr/bin/env bash

set -Eeuo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
demo_directory="${project_root}/output/demo-video"
work_directory="${demo_directory}/owner-ad-16x9-work"
asset_directory="${project_root}/tools/demo-video/owner-ad"
container_name="finacourt-owner-ad-render-16x9"
browser_image="${DEMO_VIDEO_BROWSER_IMAGE:-selenium/standalone-chromium:latest}"
vertical_output="${demo_directory}/finacourt-owner-ad-25s.mp4"
original_landscape_output="${demo_directory}/finacourt-owner-ad-25s-16x9.mp4"
legacy_v2_output="${demo_directory}/final-video-ads-finacourt.mp4"
ad_variant="${OWNER_AD_VARIANT:-v1}"
audio_source=""
audio_tempo="1"
voiceover_asset=""

case "${ad_variant}" in
    v1)
        control_scene="links"
        control_source="scene-owner-links.trimmed.mp4"
        subtitle_asset="finacourt-owner-ad-25s-16x9.srt"
        storyboard_asset="finacourt-owner-ad-storyboard-16x9.md"
        final_filename="finacourt-owner-ad-25s-16x9.mp4"
        raw_filename="finacourt-owner-ad-25s-16x9-raw.mp4"
        cover_filename="finacourt-owner-ad-cover-16x9.png"
        ;;
    v2)
        control_scene="earnings"
        control_source="scene-owner-earnings.trimmed.mp4"
        audio_source="audio-ad.wav"
        subtitle_asset="finacourt-owner-ad-25s-16x9-v2.srt"
        storyboard_asset="finacourt-owner-ad-25s-16x9-v2-storyboard.md"
        final_filename="finacourt-owner-ad-25s-16x9-v2.mp4"
        raw_filename="finacourt-owner-ad-25s-16x9-v2-raw.mp4"
        cover_filename="finacourt-owner-ad-cover-16x9-v2.png"
        ;;
    v4)
        control_scene=""
        control_source=""
        audio_source="audio-ad-2.wav"
        audio_tempo="1.06"
        voiceover_asset="finacourt-owner-ad-v4-voiceover.txt"
        subtitle_asset="finacourt-owner-ad-25s-16x9-v4.srt"
        storyboard_asset="finacourt-owner-ad-v4-storyboard.md"
        final_filename="finacourt-owner-ad-25s-16x9-v4.mp4"
        raw_filename="finacourt-owner-ad-25s-16x9-v4-raw.mp4"
        cover_filename="finacourt-owner-ad-cover-16x9-v4.png"
        ;;
    *)
        echo "Unknown OWNER_AD_VARIANT: ${ad_variant}. Use v1, v2, or v4." >&2
        exit 1
        ;;
esac

if [[ "${ad_variant}" == "v4" ]]; then
    required_scenes=(
        scene-owner-dashboard.trimmed.mp4
        scene-owner-bookings.trimmed.mp4
        scene-owner-analytics.trimmed.mp4
        scene-owner-links.trimmed.mp4
        scene-owner-earnings.trimmed.mp4
    )
else
    required_scenes=(
        scene-owner-dashboard.trimmed.mp4
        scene-owner-bookings.trimmed.mp4
        scene-owner-analytics.trimmed.mp4
        "${control_source}"
    )
fi

for scene in "${required_scenes[@]}"; do
    if [[ ! -s "${demo_directory}/${scene}" ]]; then
        echo "Missing reusable owner-demo scene: ${demo_directory}/${scene}" >&2
        if [[ "${scene}" == "scene-owner-earnings.trimmed.mp4" ]]; then
            echo "Generate it with DEMO_VIDEO_VARIANT=owner-earnings ./scripts/generate-finacourt-demo.sh" >&2
        else
            echo "Generate the existing owner demo first with DEMO_VIDEO_VARIANT=owner ./scripts/generate-finacourt-demo.sh" >&2
        fi
        exit 1
    fi
done

if [[ -n "${audio_source}" && ! -s "${demo_directory}/${audio_source}" ]]; then
    echo "Missing owner-ad audio: ${demo_directory}/${audio_source}" >&2
    exit 1
fi

if [[ ! -s "${project_root}/public/icons/finacourt-logo.png" ]]; then
    echo "Missing FinACourt logo: public/icons/finacourt-logo.png" >&2
    exit 1
fi

vertical_hash_before=""
original_landscape_hash_before=""
legacy_v2_hash_before=""
if [[ -s "${vertical_output}" ]]; then
    vertical_hash_before="$(sha256sum "${vertical_output}" | awk '{print $1}')"
fi
if [[ -s "${original_landscape_output}" ]]; then
    original_landscape_hash_before="$(sha256sum "${original_landscape_output}" | awk '{print $1}')"
fi
if [[ -s "${legacy_v2_output}" ]]; then
    legacy_v2_hash_before="$(sha256sum "${legacy_v2_output}" | awk '{print $1}')"
fi

mkdir -p "${work_directory}"
chmod 0777 "${demo_directory}" "${work_directory}"
cp "${asset_directory}/${subtitle_asset}" "${demo_directory}/${subtitle_asset}"
cp "${asset_directory}/${storyboard_asset}" "${demo_directory}/${storyboard_asset}"
if [[ -n "${voiceover_asset}" ]]; then
    cp "${asset_directory}/${voiceover_asset}" "${demo_directory}/${voiceover_asset}"
fi

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
        --window-size=1920,1168 \
        --default-background-color=00000000 \
        --screenshot="/output/demo-video/owner-ad-16x9-work/${scene}.capture.png" \
        "file:///work/owner-ad/overlay-16x9.html?scene=${scene}&variant=${ad_variant}" >/dev/null 2>&1

    # Headless Chromium reserves 88 px outside its content viewport. Capture
    # taller, then retain the exact 1920x1080 application composition.
    docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
        -i "/output/demo-video/owner-ad-16x9-work/${scene}.capture.png" \
        -vf 'crop=1920:1080:0:0' \
        "/output/demo-video/owner-ad-16x9-work/${scene}.png"
}

if [[ "${ad_variant}" == "v4" ]]; then
    overlay_scenes=(clean hook bookings analytics links earnings cta)
else
    overlay_scenes=(clean hook bookings analytics "${control_scene}" cta)
fi

for scene in "${overlay_scenes[@]}"; do
    render_overlay "${scene}"
done

encode_ui_base() {
    local source_file="$1"
    local output_file="$2"
    local trim_expression="$3"
    local duration="${4:-5}"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
        -i "/output/demo-video/${source_file}" \
        -loop 1 -framerate 30 -i /output/demo-video/owner-ad-16x9-work/clean.png \
        -filter_complex "[0:v]${trim_expression},scale=1920:1080:force_original_aspect_ratio=decrease:flags=lanczos,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setpts=PTS-STARTPTS,fps=30[ui];[1:v]format=rgba[mask];[ui][mask]overlay=0:0:eof_action=repeat:shortest=1,format=yuv420p[v]" \
        -map '[v]' -an -t "${duration}" \
        -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
        "/output/demo-video/owner-ad-16x9-work/${output_file}"
}

overlay_caption() {
    local base_file="$1"
    local overlay_file="$2"
    local output_file="$3"
    local duration="${4:-5}"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
        -i "/output/demo-video/owner-ad-16x9-work/${base_file}" \
        -loop 1 -framerate 30 -i "/output/demo-video/owner-ad-16x9-work/${overlay_file}" \
        -filter_complex '[1:v]format=rgba[copy];[0:v][copy]overlay=0:0:eof_action=repeat:shortest=1,format=yuv420p[v]' \
        -map '[v]' -an -t "${duration}" \
        -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
        "/output/demo-video/owner-ad-16x9-work/${output_file}"
}

docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
    -i /output/demo-video/scene-owner-dashboard.trimmed.mp4 \
    -loop 1 -framerate 30 -i /output/demo-video/owner-ad-16x9-work/hook.png \
    -filter_complex "[0:v]trim=start=0.4:duration=3,scale=1920:1080:force_original_aspect_ratio=decrease:flags=lanczos,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,eq=brightness=-0.08:saturation=0.85,setpts=PTS-STARTPTS,fps=30[bg];[1:v]format=rgba[copy];[bg][copy]overlay=0:0:eof_action=repeat:shortest=1,format=yuv420p[v]" \
    -map '[v]' -an -t 3 \
    -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
    /output/demo-video/owner-ad-16x9-work/scene-hook.mp4

if [[ "${ad_variant}" == "v4" ]]; then
    encode_ui_base \
        scene-owner-bookings.trimmed.mp4 \
        scene-bookings-raw.mp4 \
        'trim=start=0.4:duration=4' \
        4
    encode_ui_base \
        scene-owner-analytics.trimmed.mp4 \
        scene-analytics-raw.mp4 \
        'trim=start=0.4:duration=5' \
        5
    encode_ui_base \
        scene-owner-links.trimmed.mp4 \
        scene-links-raw.mp4 \
        'trim=start=0.4:duration=4' \
        4
    encode_ui_base \
        scene-owner-earnings.trimmed.mp4 \
        scene-earnings-raw.mp4 \
        'trim=start=0.4:duration=5' \
        5

    overlay_caption scene-bookings-raw.mp4 bookings.png scene-bookings.mp4 4
    overlay_caption scene-analytics-raw.mp4 analytics.png scene-analytics.mp4 5
    overlay_caption scene-links-raw.mp4 links.png scene-links.mp4 4
    overlay_caption scene-earnings-raw.mp4 earnings.png scene-earnings.mp4 5
    cta_duration=4
else
    encode_ui_base \
        scene-owner-bookings.trimmed.mp4 \
        scene-bookings-raw.mp4 \
        'trim=start=0.4:duration=5'
    encode_ui_base \
        scene-owner-analytics.trimmed.mp4 \
        scene-analytics-raw.mp4 \
        'trim=start=0.4:duration=5'
    if [[ "${ad_variant}" == "v2" ]]; then
        encode_ui_base \
            "${control_source}" \
            scene-control-raw.mp4 \
            'trim=start=0.4:duration=5'
    else
        encode_ui_base \
            "${control_source}" \
            scene-control-raw.mp4 \
            'trim=start=0.2:end=4.3,setpts=(PTS-STARTPTS)/0.82'
    fi

    overlay_caption scene-bookings-raw.mp4 bookings.png scene-bookings.mp4
    overlay_caption scene-analytics-raw.mp4 analytics.png scene-analytics.mp4
    overlay_caption scene-control-raw.mp4 "${control_scene}.png" scene-control.mp4
    cta_duration=6
fi

docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
    -f lavfi -i "color=c=0x03251d:s=1920x1080:r=30:d=${cta_duration}" \
    -loop 1 -framerate 30 -i /output/demo-video/owner-ad-16x9-work/cta.png \
    -filter_complex '[1:v]format=rgba[cta];[0:v][cta]overlay=0:0:eof_action=repeat:shortest=1,format=yuv420p[v]' \
    -map '[v]' -an -t "${cta_duration}" -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
    /output/demo-video/owner-ad-16x9-work/scene-cta.mp4

final_list="${work_directory}/final-scenes.txt"
raw_list="${work_directory}/raw-scenes.txt"
if [[ "${ad_variant}" == "v4" ]]; then
    printf '%s\n' \
        "file '/output/demo-video/owner-ad-16x9-work/scene-hook.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-bookings.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-analytics.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-links.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-earnings.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-cta.mp4'" \
        >"${final_list}"
    printf '%s\n' \
        "file '/output/demo-video/owner-ad-16x9-work/scene-hook.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-bookings-raw.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-analytics-raw.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-links-raw.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-earnings-raw.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-cta.mp4'" \
        >"${raw_list}"
else
    printf '%s\n' \
        "file '/output/demo-video/owner-ad-16x9-work/scene-hook.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-bookings.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-analytics.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-control.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-cta.mp4'" \
        >"${final_list}"
    printf '%s\n' \
        "file '/output/demo-video/owner-ad-16x9-work/scene-hook.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-bookings-raw.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-analytics-raw.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-control-raw.mp4'" \
        "file '/output/demo-video/owner-ad-16x9-work/scene-cta.mp4'" \
        >"${raw_list}"
fi

encode_concat() {
    local list_file="$1"
    local output_file="$2"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
        -f concat -safe 0 -i "/output/demo-video/owner-ad-16x9-work/${list_file}" \
        -vf 'fps=30,scale=1920:1080:force_original_aspect_ratio=decrease:flags=lanczos,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,format=yuv420p' \
        -an -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 \
        -movflags +faststart \
        -metadata title='FinACourt owner ad 16x9' \
        "/output/demo-video/${output_file}"
}

encode_concat final-scenes.txt "${final_filename}"
encode_concat raw-scenes.txt "${raw_filename}"

if [[ -n "${audio_source}" ]]; then
    docker exec "${container_name}" ffmpeg -hide_banner -loglevel warning -y \
        -i "/output/demo-video/${final_filename}" \
        -i "/output/demo-video/${audio_source}" \
        -filter_complex "[1:a]atempo=${audio_tempo},aresample=48000,pan=stereo|c0=c0|c1=c0,volume=-1dB,apad=pad_dur=3[a]" \
        -map 0:v:0 -map '[a]' \
        -c:v copy -c:a aac -b:a 192k -ar 48000 -ac 2 \
        -shortest -movflags +faststart \
        -metadata title='FinACourt owner ad 16x9' \
        /output/demo-video/owner-ad-16x9-work/final-with-audio.mp4

    docker exec "${container_name}" mv \
        /output/demo-video/owner-ad-16x9-work/final-with-audio.mp4 \
        "/output/demo-video/${final_filename}"
fi

docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
    -i "/output/demo-video/${final_filename}" \
    -frames:v 1 "/output/demo-video/${cover_filename}"

metadata="$(docker exec "${container_name}" ffmpeg -hide_banner -i "/output/demo-video/${final_filename}" -f null - 2>&1)"

if [[ "${metadata}" =~ Duration:\ ([0-9]{2}):([0-9]{2}):([0-9]+\.[0-9]+) ]]; then
    duration="$(awk -v hours="${BASH_REMATCH[1]}" -v minutes="${BASH_REMATCH[2]}" -v seconds="${BASH_REMATCH[3]}" 'BEGIN { printf "%.3f", (hours * 3600) + (minutes * 60) + seconds }')"
else
    echo "FFmpeg could not read the final landscape ad duration." >&2
    exit 1
fi

if ! awk -v duration="${duration}" 'BEGIN { exit !(duration >= 23 && duration <= 25) }'; then
    echo "Final duration ${duration}s is outside the required 23–25 second range." >&2
    exit 1
fi

for expected in 'Video: h264' 'yuv420p' '1920x1080' '30 fps'; do
    if [[ "${metadata}" != *"${expected}"* ]]; then
        echo "Final landscape ad validation failed: expected ${expected}." >&2
        echo "${metadata}" >&2
        exit 1
    fi
done

if [[ -n "${audio_source}" ]]; then
    for expected in 'Audio: aac' '48000 Hz' 'stereo'; do
        if [[ "${metadata}" != *"${expected}"* ]]; then
            echo "Final landscape ad validation failed: expected ${expected}." >&2
            echo "${metadata}" >&2
            exit 1
        fi
    done
fi

if [[ -n "${vertical_hash_before}" ]]; then
    vertical_hash_after="$(sha256sum "${vertical_output}" | awk '{print $1}')"
    if [[ "${vertical_hash_before}" != "${vertical_hash_after}" ]]; then
        echo "The existing 9:16 ad changed during the landscape render." >&2
        exit 1
    fi
fi

if [[ -n "${original_landscape_hash_before}" ]]; then
    original_landscape_hash_after="$(sha256sum "${original_landscape_output}" | awk '{print $1}')"
    if [[ "${original_landscape_hash_before}" != "${original_landscape_hash_after}" ]]; then
        echo "The existing 16:9 v1 ad changed during the v2 render." >&2
        exit 1
    fi
fi

if [[ -n "${legacy_v2_hash_before}" ]]; then
    legacy_v2_hash_after="$(sha256sum "${legacy_v2_output}" | awk '{print $1}')"
    if [[ "${legacy_v2_hash_before}" != "${legacy_v2_hash_after}" ]]; then
        echo "The existing legacy v2 ad changed during the render." >&2
        exit 1
    fi
fi

find "${work_directory}" -depth -delete

echo "Validated: H.264, yuv420p, 1920x1080, 30 fps, ${duration}s."
echo "Created ${demo_directory}/${final_filename}"
