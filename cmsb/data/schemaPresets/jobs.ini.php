;<?php die('This is not a program file.'); exit; ?>

_listPage = ""
_maxRecords = ""
_disableView = 1
_disableAdd = 0
menuType = "multi"
_maxRecordsPerUser = ""
_disableErase = 0
_filenameFields = ""
_detailPage = ""
listPageSearchFields = "title, content, department, salary, contact"
menuName = "Jobs"
menuOrder = 1211906968
listPageFields = "dragSortOrder, title, closing_date"
_hideRecordsFromDisabledAccounts = 0
listPageOrder = "dragSortOrder DESC"

[num]
order = 1
type = "none"
label = "Record Number"
isSystemField = 1

[createdDate]
order = 2
type = "none"
label = "Created"
isSystemField = 1

[createdByUserNum]
order = 3
type = "none"
label = "Created By"
isSystemField = 1

[updatedDate]
order = 4
type = "none"
label = "Last Updated"
isSystemField = 1

[updatedByUserNum]
order = 5
type = "none"
label = "Last Updated By"
isSystemField = 1

[dragSortOrder]
order = 6
label = "Order"
type = "none"

[title]
order = 7
type = "textfield"
label = "Title"
isRequired = 1
isPasswordField = 0
defaultValue = ""
maxLength = 0

[job_id]
order = 8
label = "Job ID "
type = "textfield"
isSystemField = 0
defaultValue = ""
isPasswordField = 0
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
charsetRule = ""
charset = ""

[department]
order = 9
label = "Department"
type = "textfield"
isSystemField = 0
defaultValue = ""
isPasswordField = 0
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
charsetRule = ""
charset = ""

[salary]
order = 10
label = "Salary"
type = "textfield"
isSystemField = 0
defaultValue = ""
isPasswordField = 0
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
charsetRule = ""
charset = ""

[closing_date]
order = 11
label = "Closing Date"
type = "date"
isUnique = 0
showTime = 0
showSeconds = 0
use24HourFormat = 0
yearRangeStart = ""
yearRangeEnd = ""

[contact]
order = 12
label = "Contact"
type = "textbox"
isSystemField = 0
defaultContent = ""
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
fieldHeight = 75
autoFormat = 1

[__separator001__]
order = 13
label = ""
type = "separator"
isSystemField = 0
separatorType = "header bar"
separatorHeader = "Description"
separatorHTML = "<tr>\n <td colspan='2'>\n </td>\n</tr>"

[summary]
order = 14
label = "Summary"
type = "textbox"
isSystemField = 0
defaultContent = ""
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
fieldHeight = 150
autoFormat = 1

[content]
order = 15
label = "Content"
type = "textbox"
isSystemField = 0
defaultContent = ""
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
fieldHeight = 300
autoFormat = 1
