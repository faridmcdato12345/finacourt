#!/usr/bin/env bash

# Produce a silent, overlay-free FinACourt brand promo from the existing
# deterministic product captures. This deliberately contains no narration,
# soundtrack, subtitles, or burned marketing copy; real product motion and a
# logo-only end card carry the message.

set -Eeuo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
demo_directory="${project_root}/output/demo-video"
work_directory="${demo_directory}/brand-promo-silent-work"
container_name="finacourt-brand-promo-silent-render"
browser_image="${DEMO_VIDEO_BROWSER_IMAGE:-selenium/standalone-chromium:latest}"

landscape_output="${demo_directory}/finacourt-brand-promo-16x9-silent.mp4"
vertical_output="${demo_directory}/finacourt-brand-promo-9x16-silent.mp4"
landscape_cover="${demo_directory}/finacourt-brand-promo-16x9-silent-cover.png"
vertical_cover="${demo_directory}/finacourt-brand-promo-9x16-silent-cover.png"
logo_file="${project_root}/public/icons/finacourt-logo.png"

required_scenes=(
    scene-owner-dashboard.trimmed.mp4
    scene-owner-promotions.trimmed.mp4
    scene-owner-loyalty.trimmed.mp4
    scene-player-mobile-discovery.trimmed.mp4
    scene-player-mobile-schedule.trimmed.mp4
    scene-player-mobile-bookings.trimmed.mp4
)

for scene in "${required_scenes[@]}"; do
    if [[ ! -s "${demo_directory}/${scene}" ]]; then
        echo "Missing reusable tutorial scene: ${demo_directory}/${scene}" >&2
        exit 1
    fi
done

if [[ ! -s "${logo_file}" ]]; then
    echo "Missing FinACourt logo: ${logo_file}" >&2
    exit 1
fi

mkdir -p "${work_directory}"
chmod 0777 "${demo_directory}" "${work_directory}"

cleanup() {
    docker rm -f "${container_name}" >/dev/null 2>&1 || true
}

trap cleanup EXIT
cleanup

docker run -d \
    --name "${container_name}" \
    -v "${project_root}/output:/output" \
    -v "${project_root}/public/icons:/icons:ro" \
    "${browser_image}" >/dev/null

for _attempt in $(seq 1 60); do
    if docker exec "${container_name}" sh -lc 'command -v ffmpeg >/dev/null'; then
        break
    fi
    sleep 1
done

if ! docker exec "${container_name}" sh -lc 'command -v ffmpeg >/dev/null'; then
    echo "The render container did not provide FFmpeg." >&2
    exit 1
fi

encode_landscape_desktop() {
    local source="$1"
    local start="$2"
    local duration="$3"
    local crop_x="$4"
    local crop_y="$5"
    local output="$6"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
        -ss "${start}" -i "/output/demo-video/${source}" -t "${duration}" \
        -vf "crop=1600:900:${crop_x}:${crop_y},scale=1920:1080:flags=lanczos,fps=30,format=yuv420p" \
        -an -c:v libx264 -preset medium -crf 18 -movflags +faststart \
        "/output/demo-video/brand-promo-silent-work/${output}"
}

encode_landscape_phone() {
    local source="$1"
    local start="$2"
    local duration="$3"
    local output="$4"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
        -ss "${start}" -i "/output/demo-video/${source}" -t "${duration}" \
        -vf "crop=608:1080:656:0,pad=1920:1080:656:0:color=0x061b2c,fps=30,format=yuv420p" \
        -an -c:v libx264 -preset medium -crf 18 -movflags +faststart \
        "/output/demo-video/brand-promo-silent-work/${output}"
}

encode_vertical_owner_player_pair() {
    local owner_source="$1"
    local owner_start="$2"
    local player_source="$3"
    local player_start="$4"
    local duration="$5"
    local output="$6"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
        -ss "${owner_start}" -i "/output/demo-video/${owner_source}" \
        -ss "${player_start}" -i "/output/demo-video/${player_source}" -t "${duration}" \
        -filter_complex "color=c=0x061b2c:s=1080x1920:r=30[bg];[0:v]scale=1000:563:force_original_aspect_ratio=decrease:flags=lanczos[owner];[1:v]crop=608:1080:656:0,scale=480:853:flags=lanczos[player];[bg][owner]overlay=(W-w)/2:150[with_owner];[with_owner][player]overlay=(W-w)/2:900,fps=30,format=yuv420p[video]" \
        -map '[video]' \
        -an -c:v libx264 -preset medium -crf 18 -movflags +faststart \
        "/output/demo-video/brand-promo-silent-work/${output}"
}

encode_vertical_phone() {
    local source="$1"
    local start="$2"
    local duration="$3"
    local output="$4"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
        -ss "${start}" -i "/output/demo-video/${source}" -t "${duration}" \
        -vf "crop=608:1080:656:0,scale=1080:1920:flags=lanczos,fps=30,format=yuv420p" \
        -an -c:v libx264 -preset medium -crf 18 -movflags +faststart \
        "/output/demo-video/brand-promo-silent-work/${output}"
}

encode_end_card() {
    local size="$1"
    local output="$2"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
        -f lavfi -i "color=c=0x061b2c:s=${size}:r=30" \
        -loop 1 -i /icons/finacourt-logo.png \
        -filter_complex "[1:v]scale=310:-1:flags=lanczos[logo];[0:v][logo]overlay=(W-w)/2:(H-h)/2:shortest=1,format=yuv420p[v]" \
        -map '[v]' -an -t 3 -c:v libx264 -preset medium -crf 18 -movflags +faststart \
        "/output/demo-video/brand-promo-silent-work/${output}"
}

# Owner control → repeat growth → player discovery → live availability →
# retained player. Deliberately fast hard cuts, no overlay text or audio.
encode_landscape_desktop scene-owner-dashboard.trimmed.mp4 0.4 4.0 160 90 landscape-01.mp4
encode_landscape_desktop scene-owner-promotions.trimmed.mp4 0.5 4.0 130 85 landscape-02.mp4
encode_landscape_desktop scene-owner-loyalty.trimmed.mp4 0.3 3.5 120 90 landscape-03.mp4
encode_landscape_phone scene-player-mobile-discovery.trimmed.mp4 0.3 4.0 landscape-04.mp4
encode_landscape_phone scene-player-mobile-schedule.trimmed.mp4 0.5 4.0 landscape-05.mp4
encode_landscape_phone scene-player-mobile-bookings.trimmed.mp4 0.4 4.0 landscape-06.mp4
encode_end_card 1920x1080 landscape-07.mp4

encode_vertical_owner_player_pair scene-owner-dashboard.trimmed.mp4 0.4 scene-player-mobile-discovery.trimmed.mp4 0.3 4.0 vertical-01.mp4
encode_vertical_owner_player_pair scene-owner-promotions.trimmed.mp4 0.5 scene-player-mobile-schedule.trimmed.mp4 0.5 4.0 vertical-02.mp4
encode_vertical_owner_player_pair scene-owner-loyalty.trimmed.mp4 0.3 scene-player-mobile-bookings.trimmed.mp4 0.4 3.5 vertical-03.mp4
encode_vertical_phone scene-player-mobile-discovery.trimmed.mp4 0.3 4.0 vertical-04.mp4
encode_vertical_phone scene-player-mobile-schedule.trimmed.mp4 0.5 4.0 vertical-05.mp4
encode_vertical_phone scene-player-mobile-bookings.trimmed.mp4 0.4 4.0 vertical-06.mp4
encode_end_card 1080x1920 vertical-07.mp4

write_concat_list() {
    local output="$1"
    shift

    : > "${work_directory}/${output}"
    for scene in "$@"; do
        printf "file '/output/demo-video/brand-promo-silent-work/%s'\n" "${scene}" >> "${work_directory}/${output}"
    done
}

write_concat_list landscape-scenes.txt \
    landscape-01.mp4 landscape-02.mp4 landscape-03.mp4 landscape-04.mp4 \
    landscape-05.mp4 landscape-06.mp4 landscape-07.mp4
write_concat_list vertical-scenes.txt \
    vertical-01.mp4 vertical-02.mp4 vertical-03.mp4 vertical-04.mp4 \
    vertical-05.mp4 vertical-06.mp4 vertical-07.mp4

encode_final() {
    local list_file="$1"
    local output_file="$2"
    local expected_size="$3"

    docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
        -f concat -safe 0 -i "/output/demo-video/brand-promo-silent-work/${list_file}" \
        -vf "fps=30,scale=${expected_size}:flags=lanczos,format=yuv420p" \
        -an -c:v libx264 -preset medium -crf 18 -movflags +faststart \
        "/output/demo-video/${output_file}"
}

encode_final landscape-scenes.txt "$(basename "${landscape_output}")" 1920:1080
encode_final vertical-scenes.txt "$(basename "${vertical_output}")" 1080:1920

docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
    -ss 0.8 -i "/output/demo-video/$(basename "${landscape_output}")" -frames:v 1 \
    "/output/demo-video/$(basename "${landscape_cover}")"
docker exec "${container_name}" ffmpeg -hide_banner -loglevel error -y \
    -ss 0.8 -i "/output/demo-video/$(basename "${vertical_output}")" -frames:v 1 \
    "/output/demo-video/$(basename "${vertical_cover}")"

validate_output() {
    local output_file="$1"
    local dimensions="$2"
    local metadata

    metadata="$(docker exec "${container_name}" ffmpeg -hide_banner -i "/output/demo-video/${output_file}" -f null - 2>&1)"
    for expected in 'Video: h264' 'yuv420p' "${dimensions}" '30 fps'; do
        if ! grep -Fq "${expected}" <<<"${metadata}"; then
            echo "Validation failed for ${output_file}: expected ${expected}." >&2
            exit 1
        fi
    done

    if grep -Fq 'Audio:' <<<"${metadata}"; then
        echo "Validation failed for ${output_file}: it must be silent." >&2
        exit 1
    fi
}

validate_output "$(basename "${landscape_output}")" 1920x1080
validate_output "$(basename "${vertical_output}")" 1080x1920

echo "Created ${landscape_output}"
echo "Created ${vertical_output}"
echo "Both cuts are intentionally silent and contain no captions or text overlays."
