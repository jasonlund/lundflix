#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -eq 0 ]; then
    echo "Usage: $0 <video.webm> [...]" >&2
    exit 1
fi

for input in "$@"; do
    if [ ! -f "$input" ]; then
        echo "File not found: $input" >&2
        exit 1
    fi

    case "$input" in
        *.webm) ;;
        *)
            echo "Expected a .webm file: $input" >&2
            exit 1
            ;;
    esac

    codec_name="$(
        ffprobe -v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 "$input"
    )"
    pix_fmt="$(
        ffprobe -v error -select_streams v:0 -show_entries stream=pix_fmt -of default=noprint_wrappers=1:nokey=1 "$input"
    )"
    width="$(
        ffprobe -v error -select_streams v:0 -show_entries stream=width -of default=noprint_wrappers=1:nokey=1 "$input"
    )"
    height="$(
        ffprobe -v error -select_streams v:0 -show_entries stream=height -of default=noprint_wrappers=1:nokey=1 "$input"
    )"
    frame_rate="$(
        ffprobe -v error -select_streams v:0 -show_entries stream=r_frame_rate -of default=noprint_wrappers=1:nokey=1 "$input"
    )"

    if [ "$codec_name" = "vp9" ] && [ "$pix_fmt" = "yuv420p" ] && [ "$width" = "768" ] && [ "$height" = "432" ] && [ "$frame_rate" = "30/1" ]; then
        echo "Skipping $input"
        continue
    fi

    tmp_output="${input%.webm}.tmp.webm"
    rm -f "$tmp_output"

    ffmpeg -y \
        -i "$input" \
        -map 0:v:0 \
        -an -sn -dn \
        -vf "scale=768:432:flags=lanczos,format=yuv420p" \
        -r 30 \
        -c:v libvpx-vp9 \
        -b:v 0 \
        -crf 36 \
        "$tmp_output"

    mv "$tmp_output" "$input"
    echo "Normalized $input"
done
