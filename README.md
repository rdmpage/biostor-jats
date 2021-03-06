biostor-jats
============

Experiments in marking up BioStor articles up using Journal Archiving Tag Set (JATS; formerly NLM DTD).

Idea is to create JATS marked-up XML for BioStor articles. Initially simply article-level metadata and links to page scans, we then add content based on analysis of the page scans and associated DjVu and ABBYY XML.

BioStor provides starting point in form of archive with article metadata in JATS XML format, images (B&W and original), and DjVu and ABBYY OCR XML for article pages.

The scripts here then extract text to create hOCR files, which can then be used to generate a PDF with searchable text, and extract figures. Here is a [live example](https://dl.dropboxusercontent.com/u/639486/65706/65706.html).

## Scripts
PHP scripts in the tools directory are used to extract and add content to the base provided by BioStor.

djvu2html converts DjVu XML to HTML including hOCR tags, see [The hOCR Embedded OCR Workflow and Output Format](https://docs.google.com/document/d/1QQnIQtvdAC_8n92-LhwPcjtAUFwBlzE8EWnKAxlgVf0).

	php tools/djvu2html.php examples/65706

abby_pictures extracts picture and table blocks from ABBYY OCR, extracts corresponding part of image and puts these in the "figures" folder. It analyses the colours in the images to decide whether to use the B&W or original image for the figure, then adds links to the JATS XML.

	php tools/abbyy_pictures.php examples/65706

hocr2pdf extracts text from the HTML files, combines it with the page scans and uses FPF to generate a PDF with searchable text.

	php tools/hocr2pdf.php examples/65706

## Stylesheet
XSLT style sheets are used to display the article. 

