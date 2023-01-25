;<?php die('This is not a program file.'); exit; ?>

menuType = "multi"
_filenameFields = ""
_detailPage = ""
listPageSearchFields = "name, pullQuote, quote, website, company"
_disableErase = 0
_maxRecordsPerUser = ""
_hideRecordsFromDisabledAccounts = 0
_disableView = 1
_disableAdd = 0
_listPage = ""
_maxRecords = ""
listPageFields = "dragSortOrder, name"
menuOrder = 1211907001
listPageOrder = "dragSortOrder DESC"
menuName = "Quotes"

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

[name]
order = 7
label = "Name"
type = "textfield"
isSystemField = 0
defaultValue = ""
isPasswordField = 0
isRequired = 1
isUnique = 0
minLength = ""
maxLength = 0
charsetRule = ""
charset = ""

[company]
order = 8
label = "Company"
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

[pullQuote]
order = 9
label = "Pull Quote (small 'sound bite' quote)"
type = "textbox"
isSystemField = 0
defaultContent = ""
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
fieldHeight = 45
autoFormat = 1

[quote]
order = 10
label = "Quote"
type = "textbox"
defaultContent = ""
description = ""
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
fieldHeight = 140
autoFormat = 1

[website]
order = 11
label = "Website"
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
