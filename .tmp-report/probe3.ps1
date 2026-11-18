$ErrorActionPreference = "Stop"
$src = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\REPORT\backup-2026-11-15\Digital_LEAP_Assignment3_Report.docx"
$dst = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\.tmp-report\probe3.docx"
Copy-Item $src $dst -Force
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $null
try {
  $doc = $word.Documents.Open($dst, $false, $false, $false)
  $needle = "6. Part F " + [char]0x2014 + " Evaluation"
  foreach ($text in @("PROBE-1 alpha", "PROBE-2 bravo", "PROBE-3 charlie")) {
    $a = $doc.Content
    $f = $a.Find
    $f.ClearFormatting()
    $f.Text = $needle
    $f.Forward = $true
    $f.Wrap = 0
    $f.MatchCase = $true
    $ok = $f.Execute()
    $a.Collapse(1)
    "iter: found=" + $ok + " anchor=" + $a.Start
    $p = $doc.Paragraphs.Add($a)
    "  new para start=" + $p.Range.Start + " end=" + $p.Range.End + " before=[" + $p.Range.Text + "]"
    $p.Range.InsertBefore($text)
    "  after insert=[" + $p.Range.Text + "]"
  }
  "paras: " + $doc.Paragraphs.Count
  $doc.Save()
  "saved"
}
finally {
  if ($doc -ne $null) { try { $doc.Close(0) } catch { } }
  try { $word.Quit() } catch { }
}
