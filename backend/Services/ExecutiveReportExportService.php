<?php
/**
 * Ownuh SAIPS - Executive report export rendering.
 */

declare(strict_types=1);

namespace SAIPS\Services;

final class ExecutiveReportExportService
{
    public function renderHtmlDocument(array $report, array $snapshot, array $meta = []): string
    {
        $title = $this->e((string)($report['report_title'] ?? 'Executive Security Posture Report'));
        $overall = $this->e((string)($report['overall_posture'] ?? 'N/A'));
        $generatedAt = $this->e((string)($meta['generated_at'] ?? date('c')));
        $provider = $this->e((string)($meta['provider'] ?? 'report'));
        $model = $this->e((string)($meta['model'] ?? ''));

        $metricCards = '';
        foreach (($report['key_metrics'] ?? []) as $metric) {
            $metricCards .= '<div class="metric"><div class="metric-label">' . $this->e((string)($metric['label'] ?? 'Metric')) . '</div><div class="metric-value">' . $this->e((string)($metric['value'] ?? '')) . '</div></div>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $title . '</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;margin:0;background:#f4f7fb;color:#1f2937}
.wrap{max-width:980px;margin:0 auto;padding:32px 24px}
.hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff;border-radius:18px;padding:28px 32px}
.hero h1{margin:0 0 8px;font-size:32px}
.hero .meta{font-size:13px;opacity:.9}
.section{background:#fff;border:1px solid #dbe3ef;border-radius:16px;padding:24px;margin-top:20px}
.section h2{margin:0 0 14px;font-size:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}
.metric{background:#eef4ff;border-radius:14px;padding:16px}
.metric-label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
.metric-value{font-size:28px;font-weight:700;margin-top:6px}
.risk{border:1px solid #fde68a;background:#fffbeb;border-radius:14px;padding:16px;margin-bottom:12px}
.tag{display:inline-block;background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:600}
ul{margin:0;padding-left:20px}
li{margin-bottom:10px}
p{line-height:1.65}
.footer{margin-top:18px;font-size:12px;color:#64748b}
@media print{body{background:#fff}.wrap{max-width:none;padding:0}.section,.hero{break-inside:avoid}}
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <h1>' . $title . '</h1>
    <div class="meta">Overall posture: ' . $overall . ' | Generated: ' . $generatedAt . ' | Provider: ' . $provider . ($model !== '' ? ' | Model: ' . $model : '') . '</div>
  </div>
  <div class="section">
    <h2>Executive Summary</h2>
    <p>' . nl2br($this->e((string)($report['executive_summary'] ?? ''))) . '</p>
  </div>
  <div class="section">
    <h2>Key Metrics</h2>
    <div class="grid">' . $metricCards . '</div>
  </div>
  <div class="section">
    <h2>Board Takeaways</h2>
    ' . $this->renderListHtml($report['board_takeaways'] ?? []) . '
  </div>
  <div class="section">
    <h2>Strengths</h2>
    ' . $this->renderListHtml($report['strengths'] ?? []) . '
  </div>
  <div class="section">
    <h2>Priority Risks</h2>
    ' . $this->renderRiskHtml($report['priority_risks'] ?? []) . '
  </div>
  <div class="section">
    <h2>Next 30 Days</h2>
    ' . $this->renderListHtml($report['next_30_days'] ?? []) . '
    <h2 style="margin-top:20px">Compliance Outlook</h2>
    <p>' . nl2br($this->e((string)($report['compliance_outlook'] ?? ''))) . '</p>
  </div>
  <div class="section">
    <h2>Live Snapshot</h2>
    <div class="grid">
      <div class="metric"><div class="metric-label">Security Score</div><div class="metric-value">' . $this->e((string)($snapshot['security_score'] ?? '0')) . '</div></div>
      <div class="metric"><div class="metric-label">Compliance Score</div><div class="metric-value">' . $this->e((string)($snapshot['compliance_score'] ?? '0')) . '%</div></div>
      <div class="metric"><div class="metric-label">MFA Coverage</div><div class="metric-value">' . $this->e((string)($snapshot['users']['mfa_coverage'] ?? '0')) . '%</div></div>
      <div class="metric"><div class="metric-label">Open Incidents</div><div class="metric-value">' . $this->e((string)($snapshot['incidents']['open_total'] ?? '0')) . '</div></div>
    </div>
    <div class="footer">This report was generated from live posture data in Ownuh SAIPS.</div>
  </div>
</div>
</body>
</html>';
    }

    public function renderEmailHtml(array $report, array $snapshot, array $meta = []): string
    {
        $title = $this->e((string)($report['report_title'] ?? 'Executive Security Posture Report'));
        $summary = nl2br($this->e((string)($report['executive_summary'] ?? '')));
        $overall = $this->e((string)($report['overall_posture'] ?? 'N/A'));
        $generatedAt = $this->e((string)($meta['generated_at'] ?? date('c')));

        $metrics = '';
        foreach (($report['key_metrics'] ?? []) as $metric) {
            $metrics .= '<tr><td style="padding:8px 0;color:#64748b">' . $this->e((string)($metric['label'] ?? 'Metric')) . '</td><td style="padding:8px 0;text-align:right;font-weight:700">' . $this->e((string)($metric['value'] ?? '')) . '</td></tr>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#f4f7fb;font-family:Segoe UI,Arial,sans-serif;color:#1f2937">
<div style="max-width:760px;margin:0 auto;padding:24px">
  <div style="background:#0f172a;color:#fff;border-radius:16px;padding:24px">
    <div style="font-size:28px;font-weight:700">' . $title . '</div>
    <div style="margin-top:8px;font-size:13px;opacity:.9">Overall posture: ' . $overall . ' | Generated: ' . $generatedAt . '</div>
  </div>
  <div style="background:#fff;border-radius:16px;padding:24px;margin-top:16px">
    <h2 style="margin:0 0 12px;font-size:20px">Executive Summary</h2>
    <p style="margin:0;line-height:1.65">' . $summary . '</p>
  </div>
  <div style="background:#fff;border-radius:16px;padding:24px;margin-top:16px">
    <h2 style="margin:0 0 12px;font-size:20px">Key Metrics</h2>
    <table style="width:100%;border-collapse:collapse">' . $metrics . '</table>
  </div>
  <div style="background:#fff;border-radius:16px;padding:24px;margin-top:16px">
    <h2 style="margin:0 0 12px;font-size:20px">Priority Risks</h2>
    ' . $this->renderEmailRiskHtml($report['priority_risks'] ?? []) . '
  </div>
  <div style="background:#fff;border-radius:16px;padding:24px;margin-top:16px">
    <h2 style="margin:0 0 12px;font-size:20px">Next 30 Days</h2>
    ' . $this->renderListHtml($report['next_30_days'] ?? []) . '
    <p style="margin-top:18px;font-size:12px;color:#64748b">Snapshot highlights: Security score ' . $this->e((string)($snapshot['security_score'] ?? '0')) . ', compliance score ' . $this->e((string)($snapshot['compliance_score'] ?? '0')) . '%, MFA coverage ' . $this->e((string)($snapshot['users']['mfa_coverage'] ?? '0')) . '%.</p>
  </div>
</div>
</body>
</html>';
    }

    public function renderPdf(array $report, array $snapshot, array $meta = []): string
    {
        $pages = $this->buildStyledPdfPages($report, $snapshot, $meta);
        if ($pages === []) {
            $pages = [''];
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $kids = [];
        $objectId = 5;
        foreach ($pages as $content) {
            $contentId = $objectId + 1;
            $kids[] = $objectId . ' 0 R';
            $objects[$objectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /ProcSet [/PDF /Text] /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objectId += 2;
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref' . "\n";
        $pdf .= '0 ' . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function buildStyledPdfPages(array $report, array $snapshot, array $meta): array
    {
        $pages = [];
        $stream = '';
        $pageNo = 0;
        $y = 0.0;
        $pageWidth = 595.0;
        $pageHeight = 842.0;
        $margin = 36.0;
        $contentWidth = $pageWidth - ($margin * 2);

        $bg = [244, 247, 251];
        $navy = [15, 39, 64];
        $teal = [21, 94, 99];
        $blue = [47, 95, 156];
        $slate = [71, 85, 105];
        $muted = [100, 116, 139];
        $line = [219, 227, 239];
        $white = [255, 255, 255];
        $green = [25, 135, 84];
        $amber = [217, 119, 6];

        $title = (string)($report['report_title'] ?? 'Executive Security Posture Report');
        $overall = (string)($report['overall_posture'] ?? 'N/A');
        $generatedAt = $this->formatPdfTimestamp((string)($meta['generated_at'] ?? date('c')));
        $provider = strtoupper((string)($meta['provider'] ?? 'report'));
        $model = (string)($meta['model'] ?? '');

        $finishPage = function () use (&$pages, &$stream, &$pageNo, $margin, $muted): void {
            if ($stream === '') {
                return;
            }

            $stream .= $this->pdfText($margin, 26, 'Ownuh SAIPS Executive Posture Report', 9, $muted, 'F1');
            $stream .= $this->pdfText(510, 26, 'Page ' . $pageNo, 9, $muted, 'F1');
            $pages[] = $stream;
            $stream = '';
        };

        $startPage = function (bool $firstPage = false) use (
            &$stream,
            &$pageNo,
            &$y,
            $pageWidth,
            $pageHeight,
            $margin,
            $contentWidth,
            $bg,
            $navy,
            $teal,
            $blue,
            $white,
            $muted,
            $title,
            $overall,
            $generatedAt,
            $provider,
            $model
        ): void {
            $pageNo++;
            $stream = $this->pdfRect(0, $pageHeight, $pageWidth, $pageHeight, $bg);

            if ($firstPage) {
                $stream .= $this->pdfRect($margin, 804, $contentWidth, 112, $navy);
                $stream .= $this->pdfRect($margin, 804, 180, 10, $teal);
                $stream .= $this->pdfText($margin + 18, 774, 'BOARD-READY SECURITY BRIEFING', 10, [191, 219, 254], 'F2');
                $stream .= $this->pdfText($margin + 18, 742, $title, 23, $white, 'F2');
                $stream .= $this->pdfText($margin + 18, 718, 'Generated ' . $generatedAt . '  |  Provider ' . $provider . ($model !== '' ? '  |  Model ' . $model : ''), 10, [219, 234, 254], 'F1');

                $stream .= $this->pdfRect(414, 782, 145, 52, $this->tint($blue, 0.10));
                $stream .= $this->pdfText(428, 760, 'OVERALL POSTURE', 9, [191, 219, 254], 'F2');
                $stream .= $this->pdfText(428, 734, strtoupper($overall), 15, $white, 'F2');
                $y = 672.0;
                return;
            }

            $stream .= $this->pdfRect($margin, 796, $contentWidth, 52, $navy);
            $stream .= $this->pdfText($margin + 16, 766, $title, 18, $white, 'F2');
            $stream .= $this->pdfText($margin + 16, 746, 'Posture ' . strtoupper($overall) . '  |  Generated ' . $generatedAt, 10, [219, 234, 254], 'F1');
            $y = 724.0;
        };

        $ensureSpace = function (float $needed) use (&$y, $startPage, $finishPage): void {
            if (($y - $needed) < 68) {
                $finishPage();
                $startPage(false);
            }
        };

        $sectionLines = function (array $items, int $wrap = 78): array {
            $lines = [];
            foreach ($items as $item) {
                foreach ($this->wrapText('- ' . trim((string)$item), $wrap) as $line) {
                    $lines[] = $line;
                }
            }
            return $lines === [] ? ['No items available.'] : $lines;
        };

        $paragraphLines = function (string $text, int $wrap = 82): array {
            $lines = $this->wrapText($text, $wrap);
            return $lines === [''] ? ['No narrative available.'] : $lines;
        };

        $drawMetricCard = function (float $x, float $top, float $width, string $label, string $value, array $accent) use (&$stream, $white, $line): void {
            $stream .= $this->pdfRect($x, $top, $width, 84, $white, $line);
            $stream .= $this->pdfRect($x, $top, $width, 8, $accent);
            $stream .= $this->pdfText($x + 14, $top - 30, strtoupper($label), 8.5, [100, 116, 139], 'F2');
            $stream .= $this->pdfText($x + 14, $top - 58, $value, 20, $accent, 'F2');
        };

        $drawCard = function (string $heading, array $lines, array $accent) use (&$stream, &$y, $margin, $contentWidth, $white, $line, $ensureSpace): void {
            $leading = 14.0;
            $headerHeight = 32.0;
            $bodyHeight = max(30.0, count($lines) * $leading);
            $height = $headerHeight + $bodyHeight + 28.0;
            $ensureSpace($height + 12.0);

            $stream .= $this->pdfRect($margin, $y, $contentWidth, $height, $white, $line);
            $stream .= $this->pdfRect($margin, $y, $contentWidth, $headerHeight, $this->tint($accent, 0.86));
            $stream .= $this->pdfRect($margin, $y, 10, $headerHeight, $accent);
            $stream .= $this->pdfText($margin + 18, $y - 21, $heading, 13, [31, 41, 55], 'F2');

            $textY = $y - 48;
            foreach ($lines as $lineText) {
                $stream .= $this->pdfText($margin + 18, $textY, $lineText, 10.5, [55, 65, 81], 'F1');
                $textY -= $leading;
            }

            $y -= $height + 12.0;
        };

        $drawRiskCards = function (array $risks) use (&$stream, &$y, $margin, $contentWidth, $white, $line, $ensureSpace, $amber, $teal, $paragraphLines): void {
            $ensureSpace(40.0);
            $stream .= $this->pdfText($margin, $y - 4, 'Priority Risks', 15, [31, 41, 55], 'F2');
            $stream .= $this->pdfRect($margin, $y - 14, 120, 4, $amber);
            $y -= 24.0;

            if ($risks === []) {
                $drawRiskLines = ['No priority risks were identified in this reporting window.'];
                $height = 74.0;
                $ensureSpace($height + 12.0);
                $stream .= $this->pdfRect($margin, $y, $contentWidth, $height, $white, $line);
                $stream .= $this->pdfText($margin + 18, $y - 30, $drawRiskLines[0], 10.5, [55, 65, 81], 'F1');
                $y -= $height + 12.0;
                return;
            }

            foreach ($risks as $risk) {
                $priority = strtolower((string)($risk['priority'] ?? 'medium'));
                $accent = match ($priority) {
                    'critical', 'high', 'sev1', 'sev2' => [185, 28, 28],
                    'low', 'sev4' => $teal,
                    default => $amber,
                };
                $title = (string)($risk['title'] ?? 'Risk');
                $impactLines = $paragraphLines('Impact: ' . (string)($risk['impact'] ?? 'Not provided.'), 76);
                $recommendationLines = $paragraphLines('Recommendation: ' . (string)($risk['recommendation'] ?? 'Not provided.'), 76);
                $height = 60.0 + (count($impactLines) * 13.0) + (count($recommendationLines) * 13.0);

                $ensureSpace($height + 12.0);
                $stream .= $this->pdfRect($margin, $y, $contentWidth, $height, $white, $line);
                $stream .= $this->pdfRect($margin, $y, 12, $height, $accent);
                $stream .= $this->pdfText($margin + 22, $y - 26, $title, 12.5, [31, 41, 55], 'F2');
                $pillWidth = 86.0;
                $stream .= $this->pdfRect($margin + $contentWidth - ($pillWidth + 18), $y - 16, $pillWidth, 22, $this->tint($accent, 0.78));
                $stream .= $this->pdfText($margin + $contentWidth - ($pillWidth + 8), $y - 31, strtoupper($priority), 8.5, $accent, 'F2');

                $textY = $y - 50.0;
                foreach ($impactLines as $lineText) {
                    $stream .= $this->pdfText($margin + 22, $textY, $lineText, 10.2, [55, 65, 81], 'F1');
                    $textY -= 13.0;
                }
                $textY -= 4.0;
                foreach ($recommendationLines as $lineText) {
                    $stream .= $this->pdfText($margin + 22, $textY, $lineText, 10.2, [55, 65, 81], 'F1');
                    $textY -= 13.0;
                }

                $y -= $height + 12.0;
            }
        };

        $startPage(true);

        $metricTop = $y;
        $metricGap = 12.0;
        $metricWidth = ($contentWidth - ($metricGap * 3)) / 4;
        $metricCards = [
            ['label' => 'Security Score', 'value' => (string)($snapshot['security_score'] ?? '0'), 'accent' => $blue],
            ['label' => 'Compliance', 'value' => (string)($snapshot['compliance_score'] ?? '0') . '%', 'accent' => $teal],
            ['label' => 'MFA Coverage', 'value' => (string)($snapshot['users']['mfa_coverage'] ?? '0') . '%', 'accent' => $green],
            ['label' => 'Open Incidents', 'value' => (string)($snapshot['incidents']['open_total'] ?? '0'), 'accent' => $amber],
        ];
        foreach ($metricCards as $index => $metric) {
            $x = $margin + ($index * ($metricWidth + $metricGap));
            $drawMetricCard($x, $metricTop, $metricWidth, $metric['label'], $metric['value'], $metric['accent']);
        }
        $y -= 102.0;

        $drawCard('Executive Summary', $paragraphLines((string)($report['executive_summary'] ?? '')), $blue);
        $drawCard('Board Takeaways', $sectionLines($report['board_takeaways'] ?? []), $teal);
        $drawCard('Strengths', $sectionLines($report['strengths'] ?? []), $green);
        $drawRiskCards($report['priority_risks'] ?? []);
        $drawCard('Next 30 Days', $sectionLines($report['next_30_days'] ?? []), $amber);
        $drawCard('Compliance Outlook', $paragraphLines((string)($report['compliance_outlook'] ?? '')), $navy);

        $snapshotLines = [];
        foreach (($report['key_metrics'] ?? []) as $metric) {
            $snapshotLines[] = (string)($metric['label'] ?? 'Metric') . ': ' . (string)($metric['value'] ?? '');
        }
        $snapshotLines[] = 'Live security score: ' . (string)($snapshot['security_score'] ?? '0');
        $snapshotLines[] = 'Active admin coverage: ' . (string)($snapshot['users']['mfa_coverage'] ?? '0') . '% MFA enabled';
        $snapshotLines[] = 'Open incidents requiring attention: ' . (string)($snapshot['incidents']['open_total'] ?? '0');
        $drawCard('Leadership Snapshot', $sectionLines($snapshotLines, 82), $blue);

        $finishPage();
        return $pages;
    }

    private function renderListHtml(array $items): string
    {
        if (empty($items)) {
            return '<p>No items available.</p>';
        }

        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . $this->e((string)$item) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    private function renderRiskHtml(array $risks): string
    {
        if (empty($risks)) {
            return '<p>No priority risks were identified.</p>';
        }

        $html = '';
        foreach ($risks as $risk) {
            $html .= '<div class="risk"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><strong>' . $this->e((string)($risk['title'] ?? 'Risk')) . '</strong><span class="tag">' . $this->e((string)($risk['priority'] ?? 'medium')) . '</span></div><p>' . $this->e((string)($risk['impact'] ?? '')) . '</p><p><strong>Recommendation:</strong> ' . $this->e((string)($risk['recommendation'] ?? '')) . '</p></div>';
        }
        return $html;
    }

    private function renderEmailRiskHtml(array $risks): string
    {
        if (empty($risks)) {
            return '<p style="margin:0">No priority risks were identified.</p>';
        }

        $html = '';
        foreach ($risks as $risk) {
            $html .= '<div style="border:1px solid #fde68a;background:#fffbeb;border-radius:12px;padding:14px;margin-bottom:12px"><div style="display:flex;justify-content:space-between;gap:12px"><strong>' . $this->e((string)($risk['title'] ?? 'Risk')) . '</strong><span style="font-size:12px;font-weight:700;color:#b45309">' . $this->e((string)($risk['priority'] ?? 'medium')) . '</span></div><p style="margin:10px 0">' . $this->e((string)($risk['impact'] ?? '')) . '</p><p style="margin:0"><strong>Recommendation:</strong> ' . $this->e((string)($risk['recommendation'] ?? '')) . '</p></div>';
        }
        return $html;
    }

    private function wrapText(string $text, int $maxChars): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        if ($normalized === '') {
            return [''];
        }

        return explode("\n", wordwrap($normalized, $maxChars, "\n", true));
    }

    private function pdfEscape(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = preg_replace('/[^\x20-\x7E]/', '-', $text) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }

    private function pdfRect(float $x, float $topY, float $width, float $height, array $fillRgb, ?array $strokeRgb = null, float $lineWidth = 1.0): string
    {
        $bottomY = $topY - $height;
        $command = '';

        if ($strokeRgb !== null) {
            $command .= sprintf('%.2F w %s RG ', $lineWidth, $this->pdfRgb($strokeRgb));
        }

        $command .= sprintf(
            '%s rg %.2F %.2F %.2F %.2F re %s' . "\n",
            $this->pdfRgb($fillRgb),
            $x,
            $bottomY,
            $width,
            $height,
            $strokeRgb !== null ? 'B' : 'f'
        );

        return $command;
    }

    private function pdfText(float $x, float $y, string $text, float $size, array $rgb, string $font = 'F1'): string
    {
        return sprintf(
            "BT %s rg /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $this->pdfRgb($rgb),
            $font,
            $size,
            $x,
            $y,
            $this->pdfEscape($text)
        );
    }

    private function pdfRgb(array $rgb): string
    {
        return sprintf(
            '%.3F %.3F %.3F',
            max(0, min(255, (int)($rgb[0] ?? 0))) / 255,
            max(0, min(255, (int)($rgb[1] ?? 0))) / 255,
            max(0, min(255, (int)($rgb[2] ?? 0))) / 255
        );
    }

    private function tint(array $rgb, float $mix = 0.82): array
    {
        $mix = max(0.0, min(1.0, $mix));
        return [
            (int)round(($rgb[0] ?? 0) * (1 - $mix) + 255 * $mix),
            (int)round(($rgb[1] ?? 0) * (1 - $mix) + 255 * $mix),
            (int)round(($rgb[2] ?? 0) * (1 - $mix) + 255 * $mix),
        ];
    }

    private function formatPdfTimestamp(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value !== '' ? $value : date('Y-m-d H:i');
        }

        return date('Y-m-d H:i', $timestamp);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
