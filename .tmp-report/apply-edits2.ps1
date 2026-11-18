$ErrorActionPreference = "Stop"
$root = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G"
if ($args.Count -ge 2) {
  $docx = [string]$args[0]
  $pdf = [string]$args[1]
} else {
  $docx = Join-Path $root "REPORT\Digital_LEAP_Assignment3_Report.docx"
  $pdf  = Join-Path $root "REPORT\Digital_LEAP_Assignment3_Report.pdf"
}
$jsonPath = Join-Path $root ".tmp-report\edits.json"
$json = Get-Content -Raw -Encoding UTF8 $jsonPath | ConvertFrom-Json

function Find-Body($doc, $from, $text) {
  $r = $doc.Range($from, $doc.Content.End)
  $f = $r.Find
  $f.ClearFormatting()
  $f.Text = $text
  $f.Forward = $true
  $f.Wrap = 0
  $f.MatchCase = $true
  $f.MatchWildcards = $false
  $ok = $f.Execute()
  if (-not $ok) { throw ("Not found in body: " + $text) }
  return $r
}

function Find-ParaIndex($doc, $text, $from) {
  $n = $doc.Paragraphs.Count
  for ($i = 1; $i -le $n; $i++) {
    $p = $doc.Paragraphs.Item($i)
    if ($p.Range.Start -lt $from) { continue }
    if ($p.Range.Text.Trim() -eq $text) { return $i }
  }
  throw ("Paragraph not found: " + $text)
}

function Copy-Font($src, $dst) {
  $sf = $src.Range.Font
  $df = $dst.Range.Font
  $df.Name = $sf.Name
  $df.Size = $sf.Size
  $df.Bold = $sf.Bold
  $df.Italic = $sf.Italic
  $df.Underline = $sf.Underline
  $df.Color = $sf.Color
}

function Apply-Template($p, $tpl) {
  $p.Style = $tpl.Style
  Copy-Font $tpl $p
  $copied = $false
  try { $p.Format = $tpl.Format; $copied = $true } catch { }
  if (-not $copied) {
    $dp = $p.Format
    $sp = $tpl.Format
    $dp.Alignment = $sp.Alignment
    $dp.SpaceBefore = $sp.SpaceBefore
    $dp.SpaceAfter = $sp.SpaceAfter
    $dp.LineSpacingRule = $sp.LineSpacingRule
    $dp.FirstLineIndent = $sp.FirstLineIndent
    $dp.LeftIndent = $sp.LeftIndent
    $dp.RightIndent = $sp.RightIndent
  }
}

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($docx, $false, $false, $false)
  $tocEnd = $doc.TablesOfContents.Item(1).Range.End
  Write-Output ("toc end: " + $tocEnd)

  $first = 0
  foreach ($pair in $json.replacements) {
    $old = [string]$pair[0]
    $new = [string]$pair[1]
    $rng = $doc.Content
    $f = $rng.Find
    $f.ClearFormatting()
    $f.Replacement.ClearFormatting()
    $ok = $f.Execute($old, $true, $false, $false, $false, $false, $true, 1, $false, $new, 2)
    if (-not $ok) { throw ("Replace failed: " + $old) }
    if ($first -eq 0) { $first = 1 }
    Write-Output ("replaced: " + $old.Substring(0, [Math]::Min(60, $old.Length)))
  }

  foreach ($pair in $json.insertsAfter) {
    $anchor = [string]$pair[0]
    $text = [string]$pair[1]
    $r = Find-Body $doc $tocEnd $anchor
    $r.InsertAfter($text)
    Write-Output ("inserted after: " + $anchor.Substring(0, [Math]::Min(60, $anchor.Length)))
  }

  $h57 = "5.7 Interface, guidance and mobile layout"
  $i57 = Find-ParaIndex $doc $h57 $tocEnd
  $tplHeading = $doc.Paragraphs.Item($i57)
  $tplBody = $doc.Paragraphs.Item($i57 + 1)

  $partF = "6. Part F " + [char]0x2014 + " Evaluation"
  $partIdx = Find-ParaIndex $doc $partF $tocEnd
  foreach ($s in $json.sections) {
    $d = $doc.Paragraphs.Item($partIdx)
    $d.Range.InsertParagraphBefore()
    $p = $doc.Paragraphs.Item($partIdx)
    $p.Range.InsertBefore([string]$s.text)
    if ([int]$s.level -eq 2) { Apply-Template $p $tplHeading } else { Apply-Template $p $tplBody }
    Write-Output ("section: [" + $p.Range.Text.Trim() + "] style=" + $p.Style.NameLocal)
    $partIdx = $partIdx + 1
  }

  $i58 = Find-ParaIndex $doc "5.8 Placement messaging" $tocEnd
  $sep = $doc.Paragraphs.Item($i58 - 1)
  if ($sep.Range.Text.Trim() -ne "") { throw "Expected an empty separator before 5.8" }
  $ft = $sep.Range.FormattedText
  $partNow = Find-ParaIndex $doc $partF $tocEnd
  $target = $doc.Paragraphs.Item($partNow).Range
  $target.Collapse(1)
  $target.FormattedText = $ft
  $doc.Paragraphs.Item($i58 - 1).Range.Delete()
  Write-Output "moved page break from before 5.8 to before Part F"

  $limIdx = Find-ParaIndex $doc ([string]$json.paragraphBefore.heading) $tocEnd
  $d = $doc.Paragraphs.Item($limIdx)
  $d.Range.InsertParagraphBefore()
  $p = $doc.Paragraphs.Item($limIdx)
  $p.Range.InsertBefore([string]$json.paragraphBefore.text)
  Apply-Template $p $tplBody
  Write-Output ("paragraphBefore: [" + $p.Range.Text.Substring(0, 60) + "]")

  $t5 = $doc.Tables.Item(7)
  foreach ($row in $json.tables.five) {
    $new = $t5.Rows.Add()
    for ($c = 0; $c -lt $row.Count; $c++) {
      $cell = $new.Cells.Item($c + 1)
      $cell.Range.Text = [string]$row[$c]
      $cell.Range.Font.Name = "Calibri"
      $cell.Range.Font.Size = 8.5
    }
  }
  Write-Output ("table 5 rows: " + $t5.Rows.Count)

  $t6 = $doc.Tables.Item(8)
  foreach ($row in $json.tables.six) {
    $new = $t6.Rows.Add()
    for ($c = 0; $c -lt $row.Count; $c++) {
      $cell = $new.Cells.Item($c + 1)
      $cell.Range.Text = [string]$row[$c]
      $cell.Range.Font.Name = "Calibri"
      $cell.Range.Font.Size = 8.5
    }
  }
  Write-Output ("table 6 rows: " + $t6.Rows.Count)

  $t7 = $doc.Tables.Item(9)
  foreach ($row in $json.tables.seven) {
    $new = $t7.Rows.Add()
    for ($c = 0; $c -lt $row.Count; $c++) {
      $cell = $new.Cells.Item($c + 1)
      $cell.Range.Text = [string]$row[$c]
      $cell.Range.Font.Name = "Calibri"
      $cell.Range.Font.Size = 8.5
    }
  }
  Write-Output ("table 7 rows: " + $t7.Rows.Count)

  $t8 = $doc.Tables.Item(10)
  $rows8 = $json.tables.eight
  for ($i = 0; $i -lt $rows8.Count; $i++) {
    if (($i + 2) -le $t8.Rows.Count) { $row = $t8.Rows.Item($i + 2) } else { $row = $t8.Rows.Add() }
    $vals = $rows8[$i]
    for ($c = 0; $c -lt 4; $c++) {
      $cell = $row.Cells.Item($c + 1)
      $cell.Range.Text = [string]$vals[$c]
      $cell.Range.Font.Name = "Calibri"
      $cell.Range.Font.Size = 8.5
    }
  }
  Write-Output ("table 8 rows: " + $t8.Rows.Count)

  $doc.Repaginate()
  $doc.TablesOfContents.Item(1).Update()
  $doc.Repaginate()
  $doc.Save()
  $doc.ExportAsFixedFormat($pdf, 17, $false, 0, 0, 0, 0, 0, $true, $true, 1, $true, $true, $false)
  Write-Output "saved docx and pdf"
}
finally {
  if ($doc -ne $null) { try { $doc.Close(0) } catch { } }
  try { $word.Quit() } catch { }
  [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
  [GC]::Collect()
  [GC]::WaitForPendingFinalizers()
}
