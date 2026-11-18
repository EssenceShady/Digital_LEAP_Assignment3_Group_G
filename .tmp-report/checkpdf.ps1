$ErrorActionPreference = "Stop"
$path = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\REPORT\Digital_LEAP_Assignment3_Report.pdf"
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($path, $false, $true, $false, $false, $false, $false, $false, $false, 0, 0, $false)
  "paras: " + $doc.Paragraphs.Count
  "pages: " + $doc.ComputeStatistics(2)
  $found = @{}
  foreach ($t in @("5.8 Placement messaging", "5.9 DIVA assistant", "D21", "D12", "ninety-one") ) { $found[$t] = 0 }
  $n = $doc.Paragraphs.Count
  for ($i = 1; $i -le $n; $i++) {
    $x = $doc.Paragraphs.Item($i).Range.Text
    foreach ($k in @($found.Keys)) { if ($x.Contains($k)) { $found[$k] = $found[$k] + 1 } }
  }
  foreach ($k in $found.Keys) { "pdf contains [" + $k + "]: " + $found[$k] }
}
finally {
  if ($doc -ne $null) { try { $doc.Close(0) } catch { } }
  try { $word.Quit() } catch { }
}
