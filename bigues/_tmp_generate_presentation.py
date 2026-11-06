from pathlib import Path
import shutil
import tempfile
from playwright.sync_api import sync_playwright

site = "http://127.0.0.1:4173/"
output = Path(r"C:\Users\LENOVO\OneDrive - UAB\Uni\4rt (2026-2027)\SEMESTRE 1\TECN. INTELIGENT\bigues\presentacio-web-club-hoquei.webm")
if output.exists():
    output = output.with_name(f"{output.stem}-2{output.suffix}")
recording_dir = Path(tempfile.mkdtemp(prefix="bigues-web-video-"))

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context(
        viewport={"width": 1366, "height": 768},
        device_scale_factor=1,
        record_video_dir=str(recording_dir),
        record_video_size={"width": 1366, "height": 768},
    )
    page = context.new_page()
    video = page.video

    page.goto(site + "index.html", wait_until="domcontentloaded")
    page.wait_for_timeout(2500)
    page.locator("#club-dades").scroll_into_view_if_needed()
    page.wait_for_timeout(2000)
    page.locator("#xarxes").scroll_into_view_if_needed()
    page.wait_for_timeout(2500)
    page.locator(".teams-section").scroll_into_view_if_needed()
    page.wait_for_timeout(2500)

    page.get_by_role("link", name="EQUIPS", exact=True).click()
    page.wait_for_load_state("domcontentloaded")
    page.wait_for_timeout(2000)
    page.locator(".roster-group-secondary").scroll_into_view_if_needed()
    page.wait_for_timeout(2500)

    page.get_by_role("link", name="EL CLUB", exact=True).click()
    page.wait_for_load_state("domcontentloaded")
    page.wait_for_timeout(2000)
    page.locator("#historia").scroll_into_view_if_needed()
    page.wait_for_timeout(2500)
    page.locator("#palmares").scroll_into_view_if_needed()
    page.wait_for_timeout(2500)
    page.locator(".club-video-section").scroll_into_view_if_needed()
    page.locator(".club-video-frame video").evaluate("video => { video.muted = true; video.play().catch(() => {}); }")
    page.wait_for_timeout(3500)

    page.get_by_role("link", name="CONTACTE", exact=True).click()
    page.wait_for_load_state("domcontentloaded")
    page.wait_for_timeout(2000)
    page.locator(".contact-section").scroll_into_view_if_needed()
    page.wait_for_timeout(2500)
    page.locator(".map-section").scroll_into_view_if_needed()
    page.wait_for_timeout(2500)

    context.close()
    video.save_as(str(output))
    browser.close()

shutil.rmtree(recording_dir, ignore_errors=True)
print(f"Video: {output}")
print(f"Bytes: {output.stat().st_size}")
