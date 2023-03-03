# drmx-integration-learnpress

DRM-X 4.0 supports encrypted video, audio and PDF. DRM-X 4.0 protected courses can prevent screen recording, display user information watermark, and bind user devices to prevent sharing accounts. Through this plugin, you can easily integrate DRM-X 4.0 system with your LearnPress system seamlessly.



# File Description

### DRM-X 4.0 Integration Core Files

- **drmx_index.php**  //DRM-X 4.0 integrated index file, it will get DRM-X 4.0 encryption parameters

- **drmx_login.php** //Verify user login information and course orders, call DRM-X 4.0 integration interface to add users, update Rights, and get licenses.

- **drmx_licError.php** //Show the error encountered when obtaining a license.

- **licstore.php** //Storage of acquired licenses.

- **includes/drm_nusoap.php** //Third-party nusoap class file to call DRM-X 4.0 XML Web Service interface.

- **public/css/login-style.css** //DRM-X 4.0 Integration Style files for pages.

- **public/images/**  //DRM-X 4.0 Integration Get license page image folder.


### Plug-in core files.

- **drmx-integration-learnpress.php** //Plug-in registration file

- **drmx-integration-learnpress-settings.php** //Plug-in settings page



# How to use the plugin?

## 1. Set DRM-X 4.0 parameters in the plug-in

### 	1. Install and activate the plugin.

### 	2. Visit the plugin settings page

### 	3. Set the DRM-X 4.0 parameters

#### 	DRM-X 4.0 parameter description:

- **DRM-X 4.0 Account:** The email address you entered when you registered your DRM-X 4.0 account.
- **DRM-X 4.0 Web Service Authentication String:** Visit the [DRM-X 4.0 website integration settings](http://4.drm-x.com/SetIntegration.aspx), Select custom login page integration and set a Web Service Authentication String for DRM-X web service. Then enter the same Authentication String in the drmx-integration-learnpress plugin settings page
- **DRM-X 4.0 XML Web Service URL:** The URL for obtaining the license when opening a protected file in Xvast.
- **DRM-X 4.0 GroupID:** The User Group ID of your DRM-X 4.0 account, please login to your DRM-X 4.0 account and visit the User Group page to check it. You only need to create one user group, please do not delete the user group in your DRM-X 4.0 account after setting.
- **DRM-X 4.0 RightsID:** The Rights ID of your DRM-X 4.0 account, please login to your DRM-X 4.0 account and visit the Rights page to check it. You only need to create one Rights, please do not delete the rights in your DRM-X 4.0 account after setting, users will automatically update the Rights when they get the license, and get the updated Rights.
- **User Bind Count:** Hardware binding restrictions for student login accounts. The user binding count is only valid for users who have not yet acquired a license. If you want to modify the user who has already acquired the license, please login your DRM-X 4.0 account, edit the user, reset the user binding count.
