#!/usr/bin/env python3
"""
Speech-to-Text Transcriber menggunakan Whisper (100% lokal, tanpa API key).
Usage: python whisper_transcribe.py <audio_file_path> [language]
Output: teks ucapan exact ke stdout
"""
import sys
import os

def setup_ffmpeg_path():
    ffmpeg_candidates = [
        os.environ.get('FFMPEG_DIR', ''),
        os.path.join(os.getcwd(), 'ffmpeg.exe'),
        os.path.join(os.getcwd(), 'bin', 'ffmpeg.exe'),
        r'C:\dev\binus\digest-edu\storage\app\bin\ffmpeg.exe',
        r'C:\dev\binus\digest-edu\vendor\bin\ffmpeg.exe',
    ]

    ffmpeg_bin_dir = os.environ.get('FFMPEG_BIN_DIR', '')
    if ffmpeg_bin_dir and ffmpeg_bin_dir not in os.environ.get('PATH', ''):
        os.environ['PATH'] = ffmpeg_bin_dir + os.pathsep + os.environ.get('PATH', '')

    for candidate in ffmpeg_candidates:
        if candidate and os.path.exists(candidate):
            parent = os.path.dirname(candidate) if os.path.isfile(candidate) else candidate
            if parent and parent not in os.environ.get('PATH', ''):
                os.environ['PATH'] = parent + os.pathsep + os.environ.get('PATH', '')
            break

setup_ffmpeg_path()

def transcribe(audio_path: str, language: str = "id") -> str:
    import whisper

    if not os.path.exists(audio_path):
        print(f"[ERROR] File tidak ditemukan: {audio_path}", file=sys.stderr)
        return ""

    # Gunakan model "small" untuk keseimbangan kecepatan dan akurasi
    # Options: tiny, base, small, medium, large
    # "small" sudah cukup akurat untuk bahasa Indonesia
    model = whisper.load_model("small")

    result = model.transcribe(
        audio_path,
        language=language,
        verbose=False,
        fp16=False,  # CPU mode
        condition_on_previous_text=True,
        word_timestamps=False,
    )

    text = result.get("text", "").strip()
    if not text:
        print("[ERROR] Whisper returned empty transcript.", file=sys.stderr)
    return text

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python whisper_transcribe.py <audio_file> [language]", file=sys.stderr)
        sys.exit(1)

    audio_file = sys.argv[1]
    lang = sys.argv[2] if len(sys.argv) > 2 else "id"

    try:
        transcript = transcribe(audio_file, lang)
        if transcript:
            print(transcript)
        else:
            print("[ERROR] No transcript produced.", file=sys.stderr)
            sys.exit(1)
    except Exception as e:
        print(f"[ERROR] Transcription failed: {e}", file=sys.stderr)
        sys.exit(1)
