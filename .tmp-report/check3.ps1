$ErrorActionPreference = "Stop"
$path = [string]$args[0]
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($path, $false, $true, $false)
  $n = $doc.Paragraphs.Count
  $targets = @("5.7 Interface, guidance and mobile layout", "5.8 Placement messaging", "5.9 DIVA assistant")
  $firstBody = @{}
  for ($i = 1; $i -le $n; $i++) {
    $p = $doc.Paragraphs.Item($i)
    $x = $p.Range.Text.Trim()
    if ($targets -contains $x -and -not $firstBody.ContainsKey($x)) {
      $firstBody[$x] = $i
      $f = $p.Range.Font
      $fp = $p.Format
      "HEAD [" + $x + "] idx=" + $i + " style=" + $p.Style.NameLocal + " font=" + $f.Name + " size=" + $f.Size + " bold=" + $f.Bold + " align=" + $fp.Alignment + " before=" + $fp.SpaceBefore + " after=" + $fp.SpaceAfter + " rule=" + $fp.LineSpacingRule + " line=" + $fp.LineSpacing + " indent=" + $fp.FirstLineIndent
      $b = $doc.Paragraphs.Item($i + 1)
      $bf = $b.Range.Font
      $bp = $b.Format
      "BODY after [" + $x + "] style=" + $b.Style.NameLocal + " font=" + $bf.Name + " size=" + $bf.Size + " align=" + $bp.Alignment + " before=" + $bp.SpaceBefore + " after=" + $bp.SpaceAfter + " rule=" + $bp.LineSpacingRule + " line=" + $bp.LineSpacing + " indent=" + $bp.FirstLineIndent
    }
  }
  foreach ($ti in 7, 8, 9, 10) {
    $t = $doc.Tables.Item($ti)
    $rows = $t.Rows.Count
    "TABLE " + $ti + " rows=" + $rows
    $from = [Math]::Max(1, $rows - 3)
    if ($ti -eq 10) { $from = 1 }
    for ($r = $from; $r -le $rows; $r++) {
      $vals = @()
      for ($c = 1; $c -le $t.Columns.Count; $c++) {
        try { $vals += $t.Cell($r, $c).Range.Text.TrimEnd([char]13, [char]7) } catch { $vals += "" }
      }
      "  R" + $r + " " + ($vals -join " | ")
    }
  }
}
finally {
  if ($doc -ne $null) { try { $doc.Close(0) } catch { } }
  try { $word.Quit() } catch { }
}
