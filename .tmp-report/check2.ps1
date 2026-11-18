$ErrorActionPreference = "Stop"
if ($args.Count -ge 1) { $path = [string]$args[0] } else { $path = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\.tmp-report\test2.docx" }
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($path, $false, $true, $false)
  $t = $doc.TablesOfContents.Item(1).Range.Text
  $t.Substring([Math]::Max(0, $t.Length - 700)) | Write-Output
  "pages: " + $doc.ComputeStatistics(2)
  "words: " + $doc.ComputeStatistics(0)
  $n = $doc.Paragraphs.Count
  for ($i = 1; $i -le $n; $i++) {
    $p = $doc.Paragraphs.Item($i)
    $x = $p.Range.Text.Trim()
    if ($x -eq "5.8 Placement messaging" -or $x -eq "5.9 DIVA assistant" -or $x -eq "6. Part F - Evaluation".Replace("-", [string][char]0x2014)) {
      "para " + $i + " style=" + $p.Style.NameLocal + " size=" + $p.Range.Font.Size + " bold=" + $p.Range.Font.Bold + " align=" + $p.Format.Alignment
    }
  }
}
finally {
  if ($doc -ne $null) { try { $doc.Close(0) } catch { } }
  try { $word.Quit() } catch { }
}
