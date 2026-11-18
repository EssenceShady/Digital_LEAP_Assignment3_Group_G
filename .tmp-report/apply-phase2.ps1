$ErrorActionPreference = "Stop"
$root = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G"
$docx = Join-Path $root "REPORT\Digital_LEAP_Assignment3_Report.docx"
$pdf  = Join-Path $root "REPORT\Digital_LEAP_Assignment3_Report.pdf"
$json = Get-Content -Raw -Encoding UTF8 (Join-Path $root ".tmp-report\phase2-edits.json") | ConvertFrom-Json

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
  if (-not $ok) { throw ("Not found in body: " + $text.Substring(0, [Math]::Min(60, $text.Length))) }
  return $r
}

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($docx, $false, $false, $false)
  $tocEnd = $doc.TablesOfContents.Item(1).Range.End
  Write-Output ("toc end: " + $tocEnd)

  foreach ($pair in $json.replacements) {
    $old = [string]$pair[0]
    $new = [string]$pair[1]
    $rng = $doc.Content
    $f = $rng.Find
    $f.ClearFormatting()
    $f.Replacement.ClearFormatting()
    $ok = $f.Execute($old, $true, $false, $false, $false, $false, $true, 1, $false, $new, 2)
    if (-not $ok) { throw ("Replace failed: " + $old.Substring(0, [Math]::Min(60, $old.Length))) }
    Write-Output ("replaced: " + $old.Substring(0, [Math]::Min(60, $old.Length)))
  }

  foreach ($pair in $json.inserts) {
    $anchor = [string]$pair[0]
    $text = [string]$pair[1]
    $r = Find-Body $doc $tocEnd $anchor
    $r.InsertAfter($text)
    Write-Output ("inserted after: " + $anchor.Substring(0, [Math]::Min(60, $anchor.Length)))
  }

  $table8 = $null
  for ($i = 1; $i -le $doc.Tables.Count; $i++) {
    $t = $doc.Tables.Item($i)
    $h = ($t.Cell(1, 1).Range.Text -replace "[\r\n\a]","").Trim()
    if ($h -eq "Area") { $table8 = $t; break }
  }
  if ($table8 -eq $null) { throw "Table 8 not found" }
  $row = $table8.Rows.Add()
  for ($c = 0; $c -lt 4; $c++) {
    $cell = $row.Cells.Item($c + 1)
    $cell.Range.Text = [string]$json.tableRow[$c]
    $cell.Range.Font.Name = "Calibri"
    $cell.Range.Font.Size = 8.5
  }
  Write-Output ("table 8 rows: " + $table8.Rows.Count)

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
