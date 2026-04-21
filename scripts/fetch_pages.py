#!/usr/bin/env python3
"""
fetch_pages.py — Descarga páginas web y las convierte a Markdown limpio.

Modos:
  • Estático (default): requests + BeautifulSoup — rápido, para HTML server-rendered
  • Browser (--browser): Playwright con Chromium — para SPAs y páginas con JavaScript

Uso:
    python scripts/fetch_pages.py <url1> [url2 ...] [--out DIR] [--browser]

Ejemplos:
    # Página estática
    python scripts/fetch_pages.py https://example.com/docs

    # Página con JavaScript (SPA como DingConnect)
    python scripts/fetch_pages.py https://www.dingconnect.com/Api/Faq --browser

    # Varias páginas con JavaScript
    python scripts/fetch_pages.py --file scripts/urls.txt --browser --out scripts/output

El Markdown resultante se guarda en scripts/output/ y puede pegarse
directamente en el chat con la IA o leerse con read_file.
"""

import argparse
import re
import sys
from pathlib import Path

import requests
from bs4 import BeautifulSoup
from markdownify import markdownify as md

HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/124.0.0.0 Safari/537.36"
    )
}

# Etiquetas que añaden ruido y no aportan contenido
NOISE_TAGS = ["script", "style", "nav", "footer", "header", "aside", "noscript"]

# Si el contenido del body es menor a esto, probablemente es JS-rendered
MIN_CONTENT_CHARS = 200


def sanitize_filename(url: str) -> str:
    """Convierte una URL en un nombre de archivo seguro."""
    name = re.sub(r"https?://", "", url)
    name = re.sub(r"[^\w\-]", "_", name)
    return name[:120] + ".md"


def html_to_markdown(html: str) -> str:
    soup = BeautifulSoup(html, "lxml")

    for tag in soup(NOISE_TAGS):
        tag.decompose()

    main = (
        soup.find("main")
        or soup.find(id=re.compile(r"content|main|body", re.I))
        or soup.find(class_=re.compile(r"content|main|article", re.I))
        or soup.body
        or soup
    )

    return md(
        str(main),
        heading_style="ATX",
        bullets="-",
        strip=["img", "a"],
    ).strip()


def fetch_static(url: str, timeout: int = 20) -> str:
    """Descarga HTML con requests (sin JavaScript)."""
    resp = requests.get(url, headers=HEADERS, timeout=timeout)
    resp.raise_for_status()
    return resp.text


def fetch_browser(url: str, wait_ms: int = 2000) -> str:
    """Descarga HTML con Playwright (renderiza JavaScript)."""
    from playwright.sync_api import sync_playwright

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(extra_http_headers={"User-Agent": HEADERS["User-Agent"]})
        page.goto(url, wait_until="networkidle", timeout=30000)
        # Espera adicional para frameworks lentos
        page.wait_for_timeout(wait_ms)
        html = page.content()
        browser.close()
    return html


def is_js_page(html: str) -> bool:
    """Heurística: si el body tiene poco texto, probablemente requiere JS."""
    soup = BeautifulSoup(html, "lxml")
    if soup.body:
        text = soup.body.get_text(strip=True)
        return len(text) < MIN_CONTENT_CHARS
    return True


def process_url(url: str, out_dir: Path, use_browser: bool, auto_detect: bool) -> None:
    print(f"→ Fetching: {url}")
    try:
        if use_browser:
            html = fetch_browser(url)
            mode = "browser"
        else:
            html = fetch_static(url)
            mode = "static"
            # Auto-detectar si la página necesita JS
            if auto_detect and is_js_page(html):
                print(f"  ⚠ Detectado JS-rendered, reintentando con browser...")
                html = fetch_browser(url)
                mode = "browser (auto)"

        content = html_to_markdown(html)
        filename = sanitize_filename(url)
        out_path = out_dir / filename
        out_path.write_text(content, encoding="utf-8")
        print(f"  ✓ Guardado [{mode}]: {out_path}  ({len(content):,} chars)")
    except requests.HTTPError as e:
        print(f"  ✗ HTTP error {e.response.status_code}: {url}", file=sys.stderr)
    except requests.RequestException as e:
        print(f"  ✗ Error de red: {e}", file=sys.stderr)
    except Exception as e:
        print(f"  ✗ Error inesperado: {e}", file=sys.stderr)


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Descarga páginas web y convierte a Markdown limpio",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument("urls", nargs="*", help="URLs a descargar")
    parser.add_argument(
        "--file", "-f",
        help="Archivo de texto con una URL por línea (# para comentarios)",
        type=Path,
    )
    parser.add_argument(
        "--out", "-o",
        help="Directorio de salida (default: scripts/output)",
        type=Path,
        default=Path("scripts/output"),
    )
    parser.add_argument(
        "--browser", "-b",
        action="store_true",
        help="Usar Playwright/Chromium para páginas con JavaScript",
    )
    parser.add_argument(
        "--no-auto",
        action="store_true",
        help="Desactivar auto-detección de páginas JS (más rápido)",
    )
    args = parser.parse_args()

    urls: list[str] = list(args.urls)

    if args.file:
        if not args.file.exists():
            print(f"ERROR: No se encontró el archivo {args.file}", file=sys.stderr)
            sys.exit(1)
        urls += [
            line.strip()
            for line in args.file.read_text(encoding="utf-8").splitlines()
            if line.strip() and not line.startswith("#")
        ]

    if not urls:
        parser.print_help()
        sys.exit(1)

    args.out.mkdir(parents=True, exist_ok=True)
    mode_label = "browser" if args.browser else "auto-detect"
    print(f"\nProcesando {len(urls)} URL(s) → {args.out}  [modo: {mode_label}]\n")

    for url in urls:
        process_url(url, args.out, use_browser=args.browser, auto_detect=not args.no_auto)

    print(f"\nListo. Archivos en: {args.out.resolve()}")


if __name__ == "__main__":
    main()
