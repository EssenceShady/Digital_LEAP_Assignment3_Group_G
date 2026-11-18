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

function Find-Range($doc, $text) {
  $r = $doc.Content
  $f = $r.Find
  $f.ClearFormatting()
  $f.Text = $text
  $f.Forward = $true
  $f.Wrap = 0
  $f.MatchCase = $true
  $f.MatchWildcards = $false
  $ok = $f.Execute()
  if (-not $ok) { throw ("Not found: " + $text) }
  return $r
}

function Format-Body($p) {
  $p.Style = -1
  $p.Range.Font.Name = "Calibri"
  $p.Range.Font.Size = 11
  $p.Format.Alignment = 3
  $p.Format.SpaceBefore = 0
  $p.Format.SpaceAfter = 6
  $p.Format.LineSpacingRule = 1
}

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($docx, $false, $false, $false)

  foreach ($pair in $json.replacements) {
    $old = [string]$pair[0]
    $new = [string]$pair[1]
    $f = $doc.Content.Find
    $f.ClearFormatting()
    $f.Replacement.ClearFormatting()
    $ok = $f.Execute($old, $true, $false, $false, $false, $false, $true, 1, $false, $new, 2)
    if (-not $ok) { throw ("Replace failed: " + $old) }
    Write-Output ("replaced: " + $old.Substring(0, [Math]::Min(50, $old.Length)))
  }

  foreach ($pair in $json.insertsAfter) {
    $anchor = [string]$pair[0]
    $text = [string]$pair[1]
    $r = Find-Range $doc $anchor
    $r.InsertAfter($text)
    Write-Output ("inserted after: " + $anchor.Substring(0, [Math]::Min(50, $anchor.Length)))
  }

  $partF = "6. Part F " + [char]0x2014 + " Evaluation"
  foreach ($s in $json.sections) {
    $a = Find-Range $doc $partF
    $a.Collapse(1)
    $p = $doc.Paragraphs.Add($a)
    $p.Range.InsertBefore([string]$s.text)
    if ([int]$s.level -eq 2) {
      $p.Style = -3
      $p.Range.Font.Name = "Calibri"
    } else {
      Format-Body $p
    }
  }
  Write-Output ("new sections: " + $json.sections.Count)

  $a = Find-Range $doc ([string]$json.paragraphBefore.heading)
  $a.Collapse(1)
  $p = $doc.Paragraphs.Add($a)
  $p.Range.InsertBefore([string]$json.paragraphBefore.text)
  Format-Body $p

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
  if ($doc -ne $null) {
    try { $doc.Close(0) } catch { }
  }
  try { $word.Quit() } catch { }
  [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
  [GC]::Collect()
  [GC]::WaitForPendingFinalizers()
}
