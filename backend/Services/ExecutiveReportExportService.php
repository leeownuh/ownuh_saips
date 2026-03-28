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
        $lines = $this->buildPdfLines($report, $snapshot, $meta);
        $linesPerPage = 46;
        $pages = array_chunk($lines, $linesPerPage);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(
            static fn(int $pageNumber): string => (string)(4 + (($pageNumber - 1) * 2)) . ' 0 R',
            range(1, count($pages))
        )) . '] /Count ' . count($pages) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $objectId = 4;
        foreach ($pages as $pageLines) {
            $content = $this->buildPdfPageStream($pageLines);
            $contentId = $objectId + 1;
            $objects[$objectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objectId += 2;
        }

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

    private function buildPdfLines(array $report, array $snapshot, array $meta): array
    {
        $lines = [];
        $append = function(string $text = '', int $gapAfter = 0) use (&$lines): void {
            $lines[] = $text;
            for ($i = 0; $i < $gapAfter; $i++) {
                $lines[] = '';
            }
        };

        $append((string)($report['report_title'] ?? 'Executive Security Posture Report'), 1);
        $append('Overall posture: ' . (string)($report['overall_posture'] ?? 'N/A'));
        $append('Generated: ' . (string)($meta['generated_at'] ?? date('c')));
        if (!empty($meta['provider'])) {
            $append('Provider: ' . (string)$meta['provider']);
        }
        if (!empty($meta['model'])) {
            $append('Model: ' . (string)$meta['model']);
        }
        $append('', 0);

        $append('Executive Summary', 0);
        foreach ($this->wrapText((string)($report['executive_summary'] ?? ''), 90) as $line) {
            $append($line);
        }
        $append('', 0);

        $append('Key Metrics', 0);
        foreach (($report['key_metrics'] ?? []) as $metric) {
            $append('- ' . (string)($metric['label'] ?? 'Metric') . ': ' . (string)($metric['value'] ?? ''));
        }
        $append('', 0);

        $append('Board Takeaways', 0);
        foreach (($report['board_takeaways'] ?? []) as $item) {
            foreach ($this->wrapText('- ' . (string)$item, 90) as $line) {
                $append($line);
            }
        }
        $append('', 0);

        $append('Strengths', 0);
        foreach (($report['strengths'] ?? []) as $item) {
            foreach ($this->wrapText('- ' . (string)$item, 90) as $line) {
                $append($line);
            }
        }
        $append('', 0);

        $append('Priority Risks', 0);
        foreach (($report['priority_risks'] ?? []) as $risk) {
            $append('* ' . (string)($risk['title'] ?? 'Risk') . ' [' . (string)($risk['priority'] ?? 'medium') . ']');
            foreach ($this->wrapText('Impact: ' . (string)($risk['impact'] ?? ''), 88) as $line) {
                $append($line);
            }
            foreach ($this->wrapText('Recommendation: ' . (string)($risk['recommendation'] ?? ''), 88) as $line) {
                $append($line);
            }
            $append('', 0);
        }

        $append('Next 30 Days', 0);
        foreach (($report['next_30_days'] ?? []) as $item) {
            foreach ($this->wrapText('- ' . (string)$item, 90) as $line) {
                $append($line);
            }
        }
        $append('', 0);
        $append('Compliance Outlook', 0);
        foreach ($this->wrapText((string)($report['compliance_outlook'] ?? ''), 90) as $line) {
            $append($line);
        }
        $append('', 0);
        $append('Snapshot Highlights', 0);
        $append('Security score: ' . (string)($snapshot['security_score'] ?? '0'));
        $append('Compliance score: ' . (string)($snapshot['compliance_score'] ?? '0') . '%');
        $append('MFA coverage: ' . (string)($snapshot['users']['mfa_coverage'] ?? '0') . '%');
        $append('Open incidents: ' . (string)($snapshot['incidents']['open_total'] ?? '0'));

        return $lines;
    }

    private function buildPdfPageStream(array $lines): string
    {
        $y = 792;
        $stream = "BT\n/F1 12 Tf\n50 " . $y . " Td\n";

        foreach ($lines as $index => $line) {
            $fontSize = $index === 0 ? 18 : 11;
            $leading = $index === 0 ? 24 : 15;
            if ($index === 0) {
                $stream .= "/F1 " . $fontSize . " Tf\n";
                $stream .= "(" . $this->pdfEscape($line) . ") Tj\n";
            } else {
                $stream .= "0 -" . $leading . " Td\n";
                if ($line === '') {
                    $stream .= "() Tj\n";
                } else {
                    $stream .= "/F1 " . $fontSize . " Tf\n";
                    $stream .= "(" . $this->pdfEscape($line) . ") Tj\n";
                }
            }
        }

        $stream .= "\nET";
        return $stream;
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

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
