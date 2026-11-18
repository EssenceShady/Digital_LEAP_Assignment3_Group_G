$ErrorActionPreference = "Stop"
$src = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\REPORT\backup-2026-11-15\Digital_LEAP_Assignment3_Report.docx"
$dst = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\.tmp-report\probe2.docx"
Copy-Item $src $dst -Force
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($dst, $false, $false, $false)
  $needle = "6. Part F " + [char]0x2014 + " Evaluation"
  $r = $doc.Content
  "content range: " + $r.Start + ".." + $r.End + " len=" + $r.Text.Length
  $f = $r.Find
  $f.ClearFormatting()
  $f.Text = $needle
  $f.Forward = $true
  $f.Wrap = 0
  $f.MatchCase = $true
  $ok = $f.Execute()
  "found: " + $ok
  "range after find: " + $r.Start + ".." + $r.End + " text=[" + $r.Text + "]"
  "content end: " + $doc.Content.End

  $idx = 0
  for ($i = 1; $i -le $doc.Paragraphs.Count; $i++) {
    $t = $doc.Paragraphs.Item($i).Range.Text
    if ($t -eq ($needle + [char]13)) { $idx = $i; break }
  }
  "heading paragraph index: " + $idx

  $h = $doc.Paragraphs.Item($idx)
  $h.Range.InsertParagraphBefore()
  $p2 = $doc.Paragraphs.Item($idx)
  "after insert, idx text: [" + $p2.Range.Text + "]"
  $p2.Range.InsertBefore("PROBE-A 5.8 Placement messaging")
  $p2.Style = -3
  "p2 text now: [" + $p2.Range.Text + "]"

  $h = $doc.Paragraphs.Item($idx + 1)
  $h.Range.InsertParagraphBefore()
  $p3 = $doc.Paragraphs.Item($idx + 1)
  $p3.Range.InsertBefore("PROBE-B body paragraph text")
  $p3.Style = -1
  "p3 text now: [" + $p3.Range.Text + "]"

  "paras total: " + $doc.Paragraphs.Count
  $doc.Save()
  "saved"
}
finally {
  if ($doc -ne $null) { try { $doc.Close(0) } catch { } }
  try { $word.Quit() } catch { }
}
