$ErrorActionPreference = "Stop"
$src = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\.tmp-report\test3.docx"
$dst = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\.tmp-report\probe4.docx"
Copy-Item $src $dst -Force
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($dst, $false, $false, $false)
  $tocEnd = $doc.TablesOfContents.Item(1).Range.End
  $partF = "6. Part F " + [char]0x2014 + " Evaluation"
  $i58 = 0
  for ($i = 1; $i -le $doc.Paragraphs.Count; $i++) {
    $p = $doc.Paragraphs.Item($i)
    if ($p.Range.Start -lt $tocEnd) { continue }
    if ($p.Range.Text.Trim() -eq "5.8 Placement messaging") { $i58 = $i; break }
  }
  "i58=" + $i58
  $brk = $doc.Paragraphs.Item($i58 - 1)
  "sep text=[" + $brk.Range.Text + "]"
  if ($brk.Range.Text.Trim() -ne "") { throw "separator paragraph not empty" }
  $ft = $brk.Range.FormattedText
  $partIdx = 0
  for ($i = 1; $i -le $doc.Paragraphs.Count; $i++) {
    $p = $doc.Paragraphs.Item($i)
    if ($p.Range.Start -lt $tocEnd) { continue }
    if ($p.Range.Text.Trim() -eq $partF) { $partIdx = $i; break }
  }
  "partIdx=" + $partIdx
  $target = $doc.Paragraphs.Item($partIdx).Range
  $target.Collapse(1)
  $target.FormattedText = $ft
  $doc.Paragraphs.Item($i58 - 1).Range.Delete()
  "done; paras=" + $doc.Paragraphs.Count
  $doc.Save()
  "saved"
}
finally {
  if ($doc -ne $null) { try { $doc.Close(0) } catch { } }
  try { $word.Quit() } catch { }
}
