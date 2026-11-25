from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
from xml.sax.saxutils import escape


ROOT = Path(__file__).resolve().parent
SOURCE = ROOT / "entrega-print-manager-2026-11-25.md"
DESTINATION = ROOT / "entrega-print-manager-2026-11-25.docx"


def paragraph(text: str, style: str = "Normal") -> str:
    return (
        f'<w:p><w:pPr><w:pStyle w:val="{style}"/></w:pPr>'
        f'<w:r><w:t xml:space="preserve">{escape(text)}</w:t></w:r></w:p>'
    )


def make_document(markdown: str) -> str:
    blocks = []
    for raw_line in markdown.splitlines():
        line = raw_line.strip()
        if not line:
            continue
        if line.startswith("### "):
            blocks.append(paragraph(line[4:], "Heading2"))
        elif line.startswith("## "):
            blocks.append(paragraph(line[3:], "Heading1"))
        elif line.startswith("# "):
            blocks.append(paragraph(line[2:], "Title"))
        elif line.startswith("- "):
            blocks.append(paragraph("•  " + line[2:].replace("**", ""), "Normal"))
        elif line.startswith("``` ") or line == "```":
            continue
        else:
            blocks.append(paragraph(line.replace("**", "").replace("`", "")))

    return (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        '<w:body>' + "".join(blocks) +
        '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>'
        '<w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080"/>'
        '</w:sectPr></w:body></w:document>'
    )


styles = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="21"/></w:rPr><w:pPr><w:spacing w:after="120"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:rPr><w:b/><w:color w:val="17365D"/><w:sz w:val="34"/></w:rPr><w:pPr><w:spacing w:before="240" w:after="240"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:rPr><w:b/><w:color w:val="1F4E79"/><w:sz w:val="27"/></w:rPr><w:pPr><w:keepNext/><w:spacing w:before="300" w:after="120"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:rPr><w:b/><w:color w:val="365F91"/><w:sz w:val="23"/></w:rPr><w:pPr><w:keepNext/><w:spacing w:before="200" w:after="80"/></w:pPr></w:style>
</w:styles>'''

content_types = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>'''

root_rels = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>'''

document_rels = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>'''


with ZipFile(DESTINATION, "w", ZIP_DEFLATED) as package:
    package.writestr("[Content_Types].xml", content_types)
    package.writestr("_rels/.rels", root_rels)
    package.writestr("word/document.xml", make_document(SOURCE.read_text(encoding="utf-8")))
    package.writestr("word/styles.xml", styles)
    package.writestr("word/_rels/document.xml.rels", document_rels)

print(DESTINATION)
