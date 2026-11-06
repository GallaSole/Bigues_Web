import os
from pathlib import Path
import subprocess

project = Path(r"C:\Users\LENOVO\OneDrive - UAB\Uni\4rt (2026-2027)\SEMESTRE 1\TECN. INTELIGENT\bigues")
local_app_data = Path(os.environ["LOCALAPPDATA"])
links_ffmpeg = local_app_data / "Microsoft" / "WinGet" / "Links" / "ffmpeg.exe"
package_root = local_app_data / "Microsoft" / "WinGet" / "Packages"
candidates = [links_ffmpeg] if links_ffmpeg.exists() else []
if package_root.exists():
    candidates.extend(package_root.rglob("ffmpeg.exe"))
if not candidates:
    raise FileNotFoundError("FFmpeg was installed, but ffmpeg.exe could not be located.")

ffmpeg = candidates[0]
source = project / "presentacio-web-club-hoquei-2.webm"
target = project / "presentacio-web-club-hoquei.mp4"
subprocess.run(
    [
        str(ffmpeg), "-y", "-i", str(source), "-an", "-c:v", "libx264",
        "-crf", "22", "-preset", "medium", "-pix_fmt", "yuv420p",
        "-movflags", "+faststart", str(target),
    ],
    check=True,
    capture_output=True,
    text=True,
)
print(f"FFmpeg: {ffmpeg}")
print(f"MP4: {target}")
print(f"Bytes: {target.stat().st_size}")
