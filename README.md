# Xibo Departure-Monitor Module

## Installation
Clone this repository into your **custom** folder in xibo and execute the [install.sh](install.sh) (You may need root privileges)

If you want to use **Wiener Linien** you need to write the key into the install.sh to the **wienerLinienKey** variable.


```bash
$ sudo ./install.sh
```

After that you can delete the **xibo-departure-monitor-module** folder.


On the left panel, under administration, go to Modules and click on Install Module and select Departure-Monitor. Now the module is installed.
xibo-departure-monitor-module

<img src="./images/image1.png">

## Usage

### Single region
If you have only one region it's recommended to set the time high so the layout isn't done too fast.

### Multiple regions
When you have multiple regions disable the loop option for the region with this module and don't make the duration too long.
