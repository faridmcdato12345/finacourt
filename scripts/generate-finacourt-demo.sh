#!/usr/bin/env bash

set -Eeuo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output_directory="${project_root}/output/demo-video"
control_directory="${output_directory}/control"
browser_name="finacourt-demo-browser"
browser_image="${DEMO_VIDEO_BROWSER_IMAGE:-selenium/standalone-chromium:latest}"
account_password="${DEMO_VIDEO_ACCOUNT_PASSWORD:-finacourt-demo-only}"
reuse_scenes="${DEMO_VIDEO_REUSE_SCENES:-0}"
start_at_scene="${DEMO_VIDEO_START_AT:-}"
variant="${DEMO_VIDEO_VARIANT:-standard}"

case "${variant}" in
    standard)
        scenes=(intro discovery venue booking owner analytics links outro)
        scene_durations=(4.5 7.5 7.0 8.9 6.7 6.7 8.5 4.8)
        final_filename="finacourt-demo.mp4"
        minimum_duration=45
        maximum_duration=60.5
        ;;
    owner)
        scenes=(owner-intro owner-dashboard owner-bookings owner-analytics owner-links owner-promotions owner-visibility owner-outro)
        scene_durations=(4.0 7.0 7.0 8.0 10.0 7.0 6.0 5.0)
        final_filename="finacourt-owner-demo.mp4"
        minimum_duration=45
        maximum_duration=60.5
        ;;
    owner-earnings)
        scenes=(owner-earnings)
        scene_durations=(6.5)
        final_filename="finacourt-owner-earnings-demo.mp4"
        minimum_duration=5.5
        maximum_duration=7.5
        ;;
    full)
        scenes=(intro discovery venue booking player-bookings player-refund owner-intro owner-dashboard owner-venues owner-bookings owner-emergency owner-team owner-promotions owner-loyalty owner-growth owner-analytics owner-links owner-visibility owner-earnings owner-outro)
        scene_durations=(4.5 7.5 7.0 8.9 7.0 7.0 4.0 7.0 7.0 7.0 7.0 7.0 7.0 8.0 7.0 8.0 10.0 6.0 6.5 5.0)
        final_filename="finacourt-full-demo.mp4"
        minimum_duration=135
        maximum_duration=145
        ;;
    *)
        echo "Unknown DEMO_VIDEO_VARIANT: ${variant}. Use standard, owner, owner-earnings, or full." >&2
        exit 1
        ;;
esac

cd "${project_root}"
mkdir -p "${control_directory}"
# The Selenium image records as its unprivileged seluser account. This directory
# contains generated artifacts only and must be writable from both containers.
chmod 0777 "${output_directory}" "${control_directory}"

app_container="$(docker compose ps -q app)"

if [[ -z "${app_container}" ]]; then
    docker compose up -d db app web node
    app_container="$(docker compose ps -q app)"
fi

network_name="$(docker inspect "${app_container}" --format '{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{"\n"}}{{end}}' | head -n 1)"

if [[ -z "${network_name}" ]]; then
    echo "Could not resolve the Docker Compose network." >&2
    exit 1
fi

cleanup() {
    docker rm -f "${browser_name}" >/dev/null 2>&1 || true
}

trap cleanup EXIT
cleanup

echo "Preparing isolated FinACourt demo data..."
docker compose exec -T app php artisan migrate --force
docker compose exec -T \
    -e DEMO_VIDEO_ACCOUNT_PASSWORD="${account_password}" \
    app php artisan finacourt:demo-video-seed

resume_reached=0
if [[ -z "${start_at_scene}" ]]; then
    resume_reached=1
fi

for scene in "${scenes[@]}"; do
    if [[ "${scene}" == "${start_at_scene}" ]]; then
        resume_reached=1
    fi

    rm -f \
        "${control_directory}/${scene}.ready" \
        "${control_directory}/${scene}.start" \
        "${control_directory}/${scene}.done" \
        "${control_directory}/${scene}.error" \
        "${control_directory}/${scene}.captured"

    if [[ "${reuse_scenes}" != "1" && ${resume_reached} -eq 1 ]]; then
        rm -f \
            "${output_directory}/scene-${scene}.mp4" \
            "${output_directory}/scene-${scene}.automation.log" \
            "${output_directory}/scene-${scene}.ffmpeg.log" \
            "${output_directory}/debug-${scene}.png"
    fi
done

if [[ -n "${start_at_scene}" && ${resume_reached} -eq 0 ]]; then
    echo "DEMO_VIDEO_START_AT scene '${start_at_scene}' is not part of the ${variant} variant." >&2
    exit 1
fi

echo "Starting the deterministic 1920x1080 browser..."
docker run -d \
    --name "${browser_name}" \
    --network "${network_name}" \
    --add-host host.docker.internal:host-gateway \
    -e SE_SCREEN_WIDTH=1920 \
    -e SE_SCREEN_HEIGHT=1080 \
    -e SE_SCREEN_DEPTH=24 \
    -e SE_NODE_MAX_SESSIONS=1 \
    -e SE_NODE_OVERRIDE_MAX_SESSIONS=true \
    -v "${project_root}/tools/demo-video:/work:ro" \
    -v "${output_directory}:/output" \
    "${browser_image}" >/dev/null

for _attempt in $(seq 1 60); do
    status="$(docker exec "${browser_name}" curl -fsS http://127.0.0.1:4444/status 2>/dev/null || true)"
    if [[ "${status}" == *'"ready": true'* || "${status}" == *'"ready":true'* ]]; then
        break
    fi
    sleep 1
done

status="$(docker exec "${browser_name}" curl -fsS http://127.0.0.1:4444/status 2>/dev/null || true)"
if [[ "${status}" != *'"ready": true'* && "${status}" != *'"ready":true'* ]]; then
    echo "Selenium did not become ready." >&2
    exit 1
fi

docker exec "${browser_name}" openssl req -x509 -newkey rsa:2048 -nodes \
    -keyout /output/mock-key.pem \
    -out /output/mock-cert.pem \
    -days 1 \
    -subj '/CN=existing-booking.test' \
    -addext 'subjectAltName=DNS:existing-booking.test' >/dev/null 2>&1
docker exec -d "${browser_name}" /opt/venv/bin/python3 /work/mock_server.py \
    --directory /work \
    --cert /output/mock-cert.pem \
    --key /output/mock-key.pem \
    --port 8443

for _attempt in $(seq 1 30); do
    if docker exec "${browser_name}" curl -kfsS https://existing-booking.test:8443/existing-booking.html >/dev/null 2>&1; then
        break
    fi
    sleep 0.5
done

record_scene() {
    local scene="$1"
    local automation_pid
    local automation_status
    local ffmpeg_pid

    echo "Recording ${scene} scene..."
    docker compose exec -T \
        -e SELENIUM_URL="http://${browser_name}:4444" \
        -e APP_URL="http://localhost:8000" \
        -e DEMO_VIDEO_ACCOUNT_PASSWORD="${account_password}" \
        -e DEMO_VIDEO_OUTPUT="/var/www/html/output/demo-video" \
        node node tools/demo-video/record-scene.mjs "${scene}" \
        >"${output_directory}/scene-${scene}.automation.log" 2>&1 &
    automation_pid=$!

    for _attempt in $(seq 1 240); do
        if [[ -f "${control_directory}/${scene}.ready" ]]; then
            break
        fi
        if ! kill -0 "${automation_pid}" 2>/dev/null; then
            wait "${automation_pid}" || true
            sed -n '1,240p' "${output_directory}/scene-${scene}.automation.log" >&2
            exit 1
        fi
        sleep 0.25
    done

    if [[ ! -f "${control_directory}/${scene}.ready" ]]; then
        echo "${scene} did not become ready for recording." >&2
        sed -n '1,240p' "${output_directory}/scene-${scene}.automation.log" >&2
        exit 1
    fi

    docker exec -d "${browser_name}" bash -lc "
        ffmpeg -hide_banner -loglevel warning -y \
            -f x11grab -draw_mouse 0 -framerate 30 -video_size 1920x1080 -i :99.0 \
            -an -c:v libx264 -preset veryfast -crf 18 -pix_fmt yuv420p -r 30 \
            /output/scene-${scene}.mp4 > /output/scene-${scene}.ffmpeg.log 2>&1
        touch /output/control/${scene}.captured
    "
    sleep 0.75
    touch "${control_directory}/${scene}.start"

    for _attempt in $(seq 1 240); do
        if [[ -f "${control_directory}/${scene}.done" || -f "${control_directory}/${scene}.error" ]]; then
            break
        fi
        if ! kill -0 "${automation_pid}" 2>/dev/null; then
            break
        fi
        sleep 0.25
    done

    ffmpeg_pid="$(docker exec "${browser_name}" bash -lc "pgrep -f '^ffmpeg .*scene-${scene}\\.mp4$' | head -n 1" 2>/dev/null || true)"
    if [[ "${ffmpeg_pid}" =~ ^[0-9]+$ ]]; then
        docker exec "${browser_name}" kill -INT "${ffmpeg_pid}" >/dev/null 2>&1 || true
    fi

    for _attempt in $(seq 1 80); do
        [[ -f "${control_directory}/${scene}.captured" ]] && break
        sleep 0.25
    done

    if [[ ! -f "${control_directory}/${scene}.captured" ]]; then
        echo "FFmpeg did not stop cleanly after the ${scene} scene." >&2
        sed -n '1,160p' "${output_directory}/scene-${scene}.ffmpeg.log" >&2
        exit 1
    fi

    set +e
    wait "${automation_pid}"
    automation_status=$?
    set -e

    if [[ ${automation_status} -ne 0 || -f "${control_directory}/${scene}.error" ]]; then
        sed -n '1,260p' "${output_directory}/scene-${scene}.automation.log" >&2
        if [[ -f "${control_directory}/${scene}.error" ]]; then
            sed -n '1,160p' "${control_directory}/${scene}.error" >&2
        fi
        exit 1
    fi

    if [[ ! -s "${output_directory}/scene-${scene}.mp4" ]]; then
        echo "FFmpeg did not produce scene-${scene}.mp4." >&2
        sed -n '1,160p' "${output_directory}/scene-${scene}.ffmpeg.log" >&2
        exit 1
    fi
}

if [[ "${reuse_scenes}" == "1" ]]; then
    echo "Reusing previously recorded scene files..."
    for scene in "${scenes[@]}"; do
        if [[ ! -s "${output_directory}/scene-${scene}.mp4" ]]; then
            echo "Cannot reuse scenes: scene-${scene}.mp4 is missing." >&2
            exit 1
        fi
    done
else
    resume_reached=0
    if [[ -z "${start_at_scene}" ]]; then
        resume_reached=1
    fi
    for scene in "${scenes[@]}"; do
        if [[ "${scene}" == "${start_at_scene}" ]]; then
            resume_reached=1
        fi
        if [[ ${resume_reached} -eq 0 ]]; then
            if [[ ! -s "${output_directory}/scene-${scene}.mp4" ]]; then
                echo "Cannot resume at ${start_at_scene}: scene-${scene}.mp4 is missing." >&2
                exit 1
            fi
            echo "Keeping previously recorded ${scene} scene..."
            continue
        fi
        record_scene "${scene}"
    done
fi

echo "Trimming recorder pre-roll and assembling the final pacing..."
for index in "${!scenes[@]}"; do
    scene="${scenes[$index]}"
    duration="${scene_durations[$index]}"
    docker exec "${browser_name}" ffmpeg -hide_banner -loglevel warning -y \
        -ss 0.70 -i "/output/scene-${scene}.mp4" -t "${duration}" \
        -an -c:v libx264 -preset veryfast -crf 18 -pix_fmt yuv420p -r 30 \
        "/output/scene-${scene}.trimmed.mp4"
done

scene_list="${output_directory}/scenes.txt"
: >"${scene_list}"
for scene in "${scenes[@]}"; do
    printf "file '/output/scene-%s.trimmed.mp4'\n" "${scene}" >>"${scene_list}"
done

echo "Encoding final H.264 MP4..."
docker exec "${browser_name}" ffmpeg -hide_banner -loglevel warning -y \
    -f concat -safe 0 -i /output/scenes.txt \
    -vf 'fps=30,scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2' \
    -an -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -r 30 -movflags +faststart \
    "/output/${final_filename}"

metadata="$(docker exec "${browser_name}" ffmpeg -hide_banner -i "/output/${final_filename}" -f null - 2>&1)"

if [[ "${metadata}" =~ Duration:\ ([0-9]{2}):([0-9]{2}):([0-9]+\.[0-9]+) ]]; then
    duration="$(awk -v hours="${BASH_REMATCH[1]}" -v minutes="${BASH_REMATCH[2]}" -v seconds="${BASH_REMATCH[3]}" 'BEGIN { printf "%.3f", (hours * 3600) + (minutes * 60) + seconds }')"
else
    echo "FFmpeg could not read the final video duration." >&2
    exit 1
fi

if ! awk -v duration="${duration}" -v minimum="${minimum_duration}" -v maximum="${maximum_duration}" 'BEGIN { exit !(duration >= minimum && duration <= maximum) }'; then
    echo "Final duration ${duration}s is outside the required ${minimum_duration}–${maximum_duration} second range." >&2
    exit 1
fi

for expected in 'Video: h264' 'yuv420p' '1920x1080' '30 fps'; do
    if [[ "${metadata}" != *"${expected}"* ]]; then
        echo "Final video validation failed: expected ${expected}." >&2
        echo "${metadata}" >&2
        exit 1
    fi
done

echo "Validated with FFmpeg: H.264, yuv420p, 1920x1080, 30 fps, ${duration}s."
echo "Created ${output_directory}/${final_filename}"
