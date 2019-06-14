# Xibo Departure-Monitor Module

## Installation

###First method:
Download the latest release [here](https://github.com/halilbahar/xibo-departure-monitor-module/releases/latest) and extract it to the custom folder. You may need root privileges.
```
sudo tar -zxvf departure-monitor.tar.gz -C /path/to/xibo/custom/
```

###Second method:
Clone this repository into your **custom** folder in xibo and execute the [install.sh](install.sh) (You may need root privileges)

If you want to use **Wiener Linien** you need to write the key into the install.sh to the **wienerLinienKey** variable.


```bash
$ sudo ./install.sh
```

After that you can delete the **xibo-departure-monitor-module** folder.

###After getting the files to custom folder
On the left panel, under administration, go to Modules and click on Install Module and select Departure-Monitor. Now the module is installed.
xibo-departure-monitor-module

<img src="./images/image1.png">

## Usage

### Single region
If you have only one region you can set the region's duration as long as you would like to.

### Multiple regions
When you have multiple regions disable the loop option for the region with this module and leave it at the default value.

Now you can edit this region:<br>
<img src="./images/form01.png" width="600">

1. Set a name for your region if you want to.

2. Here you can choose between services (for now LinzAG and Wiener Linien).

3. The API-Key field only has to be set if the chosen service requires one.

4. In this field you can set the stations you would like to display. Multiple destinations can be specified with a **";"**.  

5. If you would like to specify a duration check the box here. A new field will appear where you can set the duration in seconds. But if you leave the box unchecked the default duration will be used instead.

Moreover you can change the color theme and font as you like, but please keep in mind to use hex color codes:<br>
<p>
<img src="./images/form02.png" width="600">
<img src="./images/preview01.jpeg" width="600">
</p>

1. The first value defines a color for the header's background of this table.

2. Next it is also possible to set its font color.

3. The third field defines the color code for the font of the table body.

4. Lastly you can choose a background color for the rows of this table.