<?php
declare(strict_types=1);

/*
 * PDF A4 com a lista de participantes por horário, para impressão.
 * Usa a FPDF (src/lib/fpdf), um único arquivo PHP sem dependências.
 */

require_once __DIR__ . '/lib/fpdf/fpdf.php';

/** As fontes padrão do PDF usam Windows-1252; converte o texto UTF-8. */
function pdf_text(string $s): string
{
    return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
}

class RelogioPdf extends FPDF
{
    public string $footerText = '';

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(214, 208, 196);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(85, 96, 110);
        $this->Cell(120, 5, pdf_text($this->footerText), 0, 0, 'L');
        $this->Cell(60, 5, pdf_text('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /** Corta o texto com "..." para caber na largura informada. */
    public function fit(string $text, float $width): string
    {
        if ($this->GetStringWidth($text) <= $width) {
            return $text;
        }
        while ($text !== '' && $this->GetStringWidth($text . '...') > $width) {
            $text = substr($text, 0, -1);
        }
        return $text . '...';
    }
}

/** Gera o PDF e retorna o conteúdo binário. */
function event_pdf(array $event, array $slots, array $signups): string
{
    $navy = [31, 58, 95];
    $muted = [85, 96, 110];
    $text = [29, 36, 48];
    $left = 15.0;
    $width = 180.0;
    $timeCol = 38.0;
    $nameCol = $width - $timeCol;
    $line = 6.0;
    $bottom = 297 - 20;

    $pdf = new RelogioPdf('P', 'mm', 'A4');
    $pdf->SetTitle(pdf_text('Relógio de Oração - ' . format_date_short($event['date'])));
    $pdf->SetAuthor('IEADIP');
    $pdf->SetCreator('Relógio de Oração');
    $pdf->SetMargins($left, 15, $left);
    $pdf->SetAutoPageBreak(false);
    $pdf->AliasNbPages();
    $pdf->footerText = 'Gerado em ' . date('d/m/Y') . ' às ' . date('H:i') . '  ·  “Orai sem cessar.” 1 Ts 5:17';
    $pdf->AddPage();

    // ---- Cabeçalho -----------------------------------------------------------
    $logo = logo_file();
    $textX = $left;
    if ($logo) {
        $pdf->Image($logo, $left, 13, 0, 24);
        $textX = $left + 30;
    }
    $pdf->SetXY($textX, 14);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...$muted);
    $pdf->Cell(0, 5, pdf_text('Igreja Evangélica Assembleia de Deus Madureira'), 0, 2);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 4.5, pdf_text('Interestadual de Patrocínio/MG - IEADIP'), 0, 2);
    $pdf->Ln(1.5);
    $pdf->SetX($textX);
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetTextColor(...$navy);
    $pdf->Cell(0, 9, pdf_text('Relógio de Oração'), 0, 2);
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetTextColor(...$text);
    $pdf->Cell(0, 6, pdf_text(ucfirst(format_date_long($event['date'])) . '  ·  das ' . format_time($event['start_time']) . ' às ' . format_time($event['end_time'])), 0, 2);

    $y = max($pdf->GetY(), $logo ? 37 : 0) + 4;
    $pdf->SetDrawColor(...$navy);
    $pdf->SetLineWidth(0.6);
    $pdf->Line($left, $y, $left + $width, $y);
    $pdf->SetLineWidth(0.2);

    $total = array_sum(array_map('count', $signups));
    $empty = count(array_filter($slots, fn ($s) => empty($signups[$s['start']])));
    $pdf->SetXY($left, $y + 3);
    $pdf->SetFont('Helvetica', '', 9.5);
    $pdf->SetTextColor(...$muted);
    $pdf->Cell(0, 5, pdf_text(count($slots) . ' horários  ·  ' . $total . ' inscrição(ões)  ·  ' . $empty . ' horário(s) sem participantes'), 0, 1);
    $pdf->Ln(3);

    // ---- Tabela --------------------------------------------------------------
    $tableHeader = function () use ($pdf, $left, $timeCol, $nameCol, $navy): void {
        $pdf->SetX($left);
        $pdf->SetFillColor(...$navy);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell($timeCol, 8, pdf_text('  Horário'), 0, 0, 'L', true);
        $pdf->Cell($nameCol, 8, pdf_text('Participantes'), 0, 1, 'L', true);
    };
    $tableHeader();

    foreach ($slots as $i => $slot) {
        $people = array_map(fn ($p) => $p['name'], $signups[$slot['start']] ?? []);
        $label = $slot['start'] . ' - ' . $slot['end'];

        // Com mais de 3 nomes, usa duas colunas para economizar papel.
        $cols = count($people) > 3 ? 2 : 1;
        $colWidth = ($nameCol - 4) / $cols;
        $rows = max(1, (int) ceil(count($people) / $cols));

        $rowStart = 0;
        $continued = false;
        while ($rowStart < $rows) {
            $available = (int) floor(($bottom - $pdf->GetY() - 4) / $line);
            if ($available < 1 || ($rowStart === 0 && $rows <= 8 && $available < $rows)) {
                // Não cabe nesta página: começa o horário na próxima.
                $pdf->AddPage();
                $tableHeader();
                continue;
            }
            $chunk = min($available, $rows - $rowStart);
            $top = $pdf->GetY();
            $height = $chunk * $line + 4;

            if ($i % 2 === 1) {
                $pdf->SetFillColor(246, 243, 238);
                $pdf->Rect($left, $top, $timeCol + $nameCol, $height, 'F');
            }

            $pdf->SetXY($left, $top + 2);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->SetTextColor(...($people ? $text : [138, 28, 28]));
            $pdf->Cell($timeCol, $line, pdf_text('  ' . $label . ($continued ? ' (cont.)' : '')), 0, 0);

            if (!$people) {
                $pdf->SetFont('Helvetica', 'I', 10);
                $pdf->SetTextColor(...$muted);
                $pdf->Cell($nameCol, $line, pdf_text('Nenhum participante'), 0, 0);
            } else {
                $pdf->SetFont('Helvetica', '', 10.5);
                $pdf->SetTextColor(...$text);
                for ($r = 0; $r < $chunk; $r++) {
                    for ($c = 0; $c < $cols; $c++) {
                        $idx = $c * $rows + $rowStart + $r; // ordem por coluna
                        if (!isset($people[$idx])) {
                            continue;
                        }
                        $name = pdf_text(($idx + 1) . '. ' . $people[$idx]);
                        $pdf->SetXY($left + $timeCol + $c * $colWidth, $top + 2 + $r * $line);
                        $pdf->Cell($colWidth, $line, $pdf->fit($name, $colWidth - 3), 0, 0);
                    }
                }
            }

            $pdf->SetY($top + $height);
            $pdf->SetDrawColor(214, 208, 196);
            $pdf->Line($left, $pdf->GetY(), $left + $width, $pdf->GetY());
            $rowStart += $chunk;
            $continued = true;
        }
    }

    return $pdf->Output('S');
}
