;<?php die('This is not a program file.'); exit; ?>

_listPage = ""
_hideRecordsFromDisabledAccounts = 0
listPageFields = "dragSortOrder, name, title"
menuName = "Bios"
_disableErase = 0
_detailPage = ""
menuOrder = 1211924024
_filenameFields = ""
_disableView = 1
_disableAdd = 0
menuType = "multi"
_maxRecords = ""
_maxRecordsPerUser = ""
listPageSearchFields = "name, biography"
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

[title]
order = 8
label = "Title"
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

[email]
order = 9
label = "Email"
type = "textfield"
defaultValue = ""
description = ""
fieldWidth = ""
isPasswordField = 0
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
charsetRule = ""
charset = ""

[__separator001__]
order = 10
label = ""
type = "separator"
isSystemField = 0
separatorType = "blank line"
separatorHeader = ""
separatorHTML = "<tr>\n <td colspan='2'>\n </td>\n</tr>"

[biography]
order = 11
label = "Biography"
type = "wysiwyg"
isSystemField = 0
defaultContent = ""
allowUploads = 1
isRequired = 0
isUnique = 0
minLength = ""
maxLength = ""
fieldHeight = 200
allowedExtensions = "gif,jpg,jpeg,png,svg,webp"
checkMaxUploadSize = 1
maxUploadSizeKB = 5120
checkMaxUploads = 1
maxUploads = 25
resizeOversizedImages = 1
maxImageHeight = 800
maxImageWidth = 600
createThumbnails = 1
maxThumbnailHeight = 150
maxThumbnailWidth = 150
useCustomUploadDir = 0
customUploadDir = ""
customUploadUrl = ""

[__separator002__]
order = 12
label = ""
type = "separator"
isSystemField = 0
separatorType = "blank line"
separatorHeader = ""
separatorHTML = "<tr>\n <td colspan='2'>\n </td>\n</tr>"

[photo]
order = 13
label = "Photo"
type = "upload"
isSystemField = 0
isRequired = 0
allowedExtensions = "gif,jpg,jpeg,png,svg,webp"
checkMaxUploadSize = 1
maxUploadSizeKB = 1024
checkMaxUploads = 1
maxUploads = 1
resizeOversizedImages = 1
maxImageHeight = 800
maxImageWidth = 800
createThumbnails = 1
maxThumbnailHeight = 175
maxThumbnailWidth = 175
useCustomUploadDir = 0
infoField1 = "Title"
infoField2 = "Caption"
infoField3 = ""
infoField4 = ""
infoField5 = ""
