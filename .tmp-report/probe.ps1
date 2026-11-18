$ErrorActionPreference = "Stop"
$path = "C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G\REPORT\Digital_LEAP_Assignment3_Report.docx"
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $word.Documents.Open($path, $false, $true, $false)
"tables: " + $doc.Tables.Count
"paras: " + $doc.Paragraphs.Count
"toc: " + $doc.TablesOfContents.Count
for ($i = 1; $i -le $doc.Tables.Count; $i++) {
  $t = $doc.Tables.Item($i)
  $first = $t.Cell(1,1).Range.Text.Trim()
  "$i`trows=" + $t.Rows.Count + "`tcols=" + $t.Columns.Count + "`t" + $first
}
$doc.Close(0)
$word.Quit()
