import asyncio
import hmac
import os
import pathlib
import subprocess
import tempfile
import zipfile

from fastapi import Depends, FastAPI, Header, HTTPException, UploadFile
from fastapi.responses import Response


API_TOKEN = os.environ.get("CONVERTER_API_TOKEN", "")
MAX_INPUT_BYTES = int(os.environ.get("CONVERTER_MAX_INPUT_BYTES", str(10 * 1024 * 1024)))
MAX_EXPANDED_BYTES = int(os.environ.get("CONVERTER_MAX_EXPANDED_BYTES", str(30 * 1024 * 1024)))
TIMEOUT_SECONDS = int(os.environ.get("CONVERTER_TIMEOUT_SECONDS", "90"))
SOFFICE_BINARY = os.environ.get("SOFFICE_BINARY", "/usr/bin/soffice")

if len(API_TOKEN) < 32:
    raise RuntimeError("CONVERTER_API_TOKEN must contain at least 32 characters")

app = FastAPI(title="MBG Document Converter", docs_url=None, redoc_url=None, openapi_url=None)
conversion_lock = asyncio.Lock()


def authorize(authorization: str | None = Header(default=None)) -> None:
    expected = f"Bearer {API_TOKEN}"
    if authorization is None or not hmac.compare_digest(authorization, expected):
        raise HTTPException(status_code=401, detail="Unauthorized")


def inspect_docx(path: pathlib.Path) -> None:
    try:
        with zipfile.ZipFile(path) as archive:
            names = archive.namelist()
            if "[Content_Types].xml" not in names or "word/document.xml" not in names:
                raise HTTPException(status_code=422, detail="Invalid DOCX structure")

            expanded = 0
            for item in archive.infolist():
                expanded += item.file_size
                if expanded > MAX_EXPANDED_BYTES:
                    raise HTTPException(status_code=413, detail="Expanded DOCX is too large")
                lowered = item.filename.lower()
                if "vbaproject.bin" in lowered:
                    raise HTTPException(status_code=422, detail="Macros are not allowed")
                if lowered.endswith(".rels"):
                    relations = archive.read(item).decode("utf-8", errors="ignore")
                    if 'TargetMode="External"' in relations or "TargetMode='External'" in relations:
                        raise HTTPException(status_code=422, detail="External resources are not allowed")
    except zipfile.BadZipFile as exception:
        raise HTTPException(status_code=422, detail="Invalid DOCX archive") from exception


@app.get("/health", dependencies=[Depends(authorize)])
def health() -> dict[str, str]:
    try:
        result = subprocess.run(
            [SOFFICE_BINARY, "--version"],
            check=True,
            capture_output=True,
            text=True,
            timeout=10,
        )
    except (OSError, subprocess.SubprocessError) as exception:
        raise HTTPException(status_code=503, detail="LibreOffice unavailable") from exception

    version = result.stdout.strip().replace("LibreOffice", "").strip()
    return {"status": "ok", "version": version}


@app.post("/convert", dependencies=[Depends(authorize)])
async def convert(document: UploadFile) -> Response:
    async with conversion_lock:
        with tempfile.TemporaryDirectory(prefix="mbg-converter-") as directory:
            root = pathlib.Path(directory)
            source = root / "document.docx"
            output = root / "output"
            profile = root / "profile"
            output.mkdir(mode=0o700)
            profile.mkdir(mode=0o700)

            total = 0
            with source.open("wb") as handle:
                while chunk := await document.read(1024 * 1024):
                    total += len(chunk)
                    if total > MAX_INPUT_BYTES:
                        raise HTTPException(status_code=413, detail="DOCX is too large")
                    handle.write(chunk)

            inspect_docx(source)
            command = [
                SOFFICE_BINARY,
                "--headless",
                "--nologo",
                "--nodefault",
                "--nolockcheck",
                "--nofirststartwizard",
                f"-env:UserInstallation={profile.as_uri()}",
                "--convert-to",
                "pdf:writer_pdf_Export",
                "--outdir",
                str(output),
                str(source),
            ]
            try:
                subprocess.run(
                    command,
                    check=True,
                    capture_output=True,
                    timeout=TIMEOUT_SECONDS,
                    env={**os.environ, "HOME": str(root)},
                )
            except subprocess.TimeoutExpired as exception:
                raise HTTPException(status_code=504, detail="Conversion timed out") from exception
            except (OSError, subprocess.CalledProcessError) as exception:
                raise HTTPException(status_code=502, detail="Conversion failed") from exception

            pdf = output / "document.pdf"
            if not pdf.is_file():
                raise HTTPException(status_code=502, detail="PDF output not found")
            content = pdf.read_bytes()
            if not content.startswith(b"%PDF-") or len(content) > 30 * 1024 * 1024:
                raise HTTPException(status_code=502, detail="Invalid PDF output")

            return Response(
                content=content,
                media_type="application/pdf",
                headers={"Cache-Control": "no-store", "X-Content-Type-Options": "nosniff"},
            )
