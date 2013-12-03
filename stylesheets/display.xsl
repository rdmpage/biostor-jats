<?xml version="1.0"?>
<xsl:stylesheet xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:output encoding="utf-8" indent="yes" method="html" version="1.0"/>
	<xsl:template match="/">
		<html>
			<head>
				<meta charset="utf-8"/>
			</head>
			<body style="font-family:sans-serif;background-color:rgb(246,237,225);">
				<div>
					<p style="font-size:80%">
						<xsl:value-of select="//journal-meta/journal-title-group/journal-title"/>
						<xsl:text> </xsl:text>
						<xsl:if test="//article-meta/pub-date/day">
							<xsl:value-of select="//article-meta/pub-date/day"/>
							<xsl:text> </xsl:text>
						</xsl:if>
						<xsl:if test="//article-meta/pub-date/month">
							<xsl:choose>
								<xsl:when test="//article-meta/pub-date/month = 1">
									<xsl:text>January</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 2">
									<xsl:text>February</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 3">
									<xsl:text>March</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 4">
									<xsl:text>April</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 5">
									<xsl:text>May</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 6">
									<xsl:text>June</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 7">
									<xsl:text>July</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 8">
									<xsl:text>August</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 9">
									<xsl:text>September</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 10">
									<xsl:text>October</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 11">
									<xsl:text>November</xsl:text>
								</xsl:when>
								<xsl:when test="//article-meta/pub-date/month = 12">
									<xsl:text>December</xsl:text>
								</xsl:when>
							</xsl:choose>
							<xsl:text> </xsl:text>
						</xsl:if>
						<xsl:value-of select="//article-meta/pub-date/year"/>
						<xsl:text> </xsl:text>
						<xsl:value-of select="//article-meta/volume"/>
						<xsl:if test="//article-meta/issue">
							<xsl:text>(</xsl:text>
							<xsl:value-of select="//article-meta/issue"/>
							<xsl:text>)</xsl:text>
						</xsl:if>
						<xsl:text>: </xsl:text>
						<xsl:value-of select="//article-meta/fpage"/>
						<xsl:text>-</xsl:text>
						<xsl:value-of select="//article-meta/lpage"/>
					</p>
					<h1>
						<xsl:value-of select="//article-title"/>
					</h1>
					<div>
						<xsl:apply-templates select="//contrib-group/contrib[@contrib-type='author']/name"/>
					</div>
					<xsl:if test="//abstrac">
						<h2>Abstract</h2>
						<xsl:value-of select="//abstract"/>
					</xsl:if>
					<h2>Full text</h2>
					<p>
	Full text is available as a scanned copy of the original print version. 
	Get a printable copy (PDF file) of the <u>complete article</u>, or click on a page image 
	below to browse HTML for page.
	
	<xsl:if test="//back">
		Links are also available for <a href="#reference-sec">Selected References</a>.
	</xsl:if>
	</p>
					<div>
						<xsl:apply-templates select="//supplementary-material/graphic"/>
					</div>
					<div style="clear:both;"/>
					<xsl:if test="//floats-group">
						<h2>Images in this article</h2>
						<p>Figures and tables extracted from ABBYY OCR XML.</p>
						<div>
							<xsl:apply-templates select="//fig"/>
						</div>
						<div style="clear:both;"/>
					</xsl:if>
					<xsl:if test="//back">
						<h2 id="reference-sec">Selected references</h2>
						<xsl:apply-templates select="//back"/>
					</xsl:if>
				</div>
			</body>
		</html>
	</xsl:template>
	<xsl:template match="name">
		<xsl:if test="position() != 1">
			<xsl:text>, </xsl:text>
		</xsl:if>
		<xsl:value-of select="given-names"/>
		<xsl:text> </xsl:text>
		<xsl:value-of select="surname"/>
	</xsl:template>
	<!-- scanned page -->
	<xsl:template match="//supplementary-material/graphic">
		<div style="float:left;padding:20px;">
			<!-- link to HTML -->
			<a>
				<xsl:attribute name="href">
					<xsl:text>html/</xsl:text>
					<xsl:value-of select="@xlink:role"/>
					<xsl:text>.html</xsl:text>
				</xsl:attribute>
				<img height="140">
					<xsl:attribute name="src">
						<!--
					<xsl:text>Med_Hist_1985_Jan_29(1)_1-32/</xsl:text>
					<xsl:value-of select="substring-before(@xlink:href, '.tif')" /> 
					<xsl:text>.gif</xsl:text>
				-->
						<xsl:value-of select="@xlink:href"/>
					</xsl:attribute>
				</img>
			</a>
			<!-- PageID -->
			<div style="text-align:center">
				<xsl:value-of select="@xlink:role"/>
			</div>
		</div>
	</xsl:template>
	<xsl:template match="fig">
		<xsl:apply-templates select="graphic"/>
	</xsl:template>
	<!-- figure or table -->
	<xsl:template match="graphic">
		<div style="float:left;padding:20px;">
			<a>
				<xsl:attribute name="href">
					<xsl:value-of select="@xlink:href"/>
				</xsl:attribute>
				<img height="100">
					<xsl:attribute name="src">
						<xsl:value-of select="@xlink:href"/>
					</xsl:attribute>
				</img>
			</a>
		</div>
	</xsl:template>
	<xsl:template match="back">
		<xsl:apply-templates select="ref-list"/>
	</xsl:template>
	<xsl:template match="ref-list">
		<ul>
			<xsl:apply-templates select="ref"/>
		</ul>
	</xsl:template>
	<xsl:template match="ref">
		<li>
			<xsl:apply-templates select="mixed-citation"/>
		</li>
	</xsl:template>
	<xsl:template match="mixed-citation">
		<xsl:value-of select="."/>
		<xsl:apply-templates select="ext-link"/>
	</xsl:template>
	<xsl:template match="ext-link">
		<xsl:variable name="uri" select="@xlink:href"/>
		<xsl:if test="contains($uri, 'http://dx.doi.org/')">
			<xsl:text> DOI: </xsl:text>
			<a>
				<xsl:attribute name="href">
					<xsl:value-of select="$uri"/>
				</xsl:attribute>
				<xsl:value-of select="substring-after($uri, 'http://dx.doi.org/')"/>
			</a>
		</xsl:if>
	</xsl:template>
</xsl:stylesheet>
