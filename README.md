# Statseeker - Network Weathermap Integration

This project provides the files needed to use Network Weathermap to display interface utilization data from Statseeker.


## Prerequisites

* A Statseeker v5.2, or later, server
* A Network Weathermap server (cannot be installed on the Statseeker server), see https://network-weathermap.com/ for links to download packages and instructions on installation, and basic configuration of Network Weathermap for your environment.


## Installing

1. Extract the package to a directory on the Weathermap server
E.g. `/home/user1/ss-wm`

## Configuration
There are 4 files to be edited:

* etc/ss-wm.cfg
* bin/ss-wm.sh
* bin/ss-wm-api.php
* lib/WeatherMapDataSource_statseeker.php

### ss-wm.cfg

Parameter | Value
---- | ----|
WEATHERMAP_BIN | Path to the Weathermap binary. E.g. ```/var/www/html/weathermap/weathermap```.
WEATHERMAP_CONF|Path to the Weathermap configuration file, this is created when you build your weathermap using the editor.php. E.g. ```/var/www/html/configs/statseeker.conf```.
WEATHERMAP_HTML|Path, relative to the weathermap binary, to the Weathermap html file. E.g. ```index.html```.
WEATHERMAP_IMG|Path, relative to the weathermap binary, to the Weathermap image (this is the image displayed when viewing your weathermap). E.g. ```statseeker.png```.
TMP_DIR|Path for temporary storage of graphs during processing. E.g. ```/tmp/statseeker/``` **Note:** the contents of this folder will be edited by the web server agent, typically this is *"www-data"*. For this to occur, the TMP_DIR directory permissions may need to be updated to assign ownership of the directory to this user agent, and to assign read/write access to the folder and its files to this user agent.
WEB_DIR | Root of the web tree where the weathermap exists. E.g. ```/var/www/html/weathermap```.
GRAPH_DIR | Final location of the popup graphs. E.g. ```/var/www/html/weathermap/graph```.
INSTALL_DIR | Directory where your ss-wm package is installed. E.g. ```/home/user1/ss-wm```.
USERNAME | The Statseeker user account employed to collect the data for the weathermap. The associated password will be kept in clear text in the config file so we suggest that you create a Statseeker user account specifically for this purpose. **Note:** ensure that the user account has access to the interfaces that will be used to populate the weathermap.
PASSWORD | Password of the Statseeker user account employed to collect the data for the weathermap.
GROUP | Statseeker group name. **Note:** to ensure the best performance, restrict the group contents to only contain those interfaces that are to be used to populate the weathermap.
STATSEEKER | Hostname or IP address of the Statseeker server.
URL | URL of the **Interfaces -> Top Utilization Graphs** report. To get this, run the report from Statseeker, and copy the URL.

E.g.: 
```
WEATHERMAP_BIN="/var/www/html/weathermap/weathermap"
WEATHERMAP_CONF="/var/www/html/weathermap/configs/statseeker.conf"
WEATHERMAP_HTML="/var/www/html/weathermap/index.html"
WEATHERMAP_IMG="statseeker.png"
TMP_DIR="/tmp/statseeker"
WEB_DIR="/var/www/html"
GRAPH_DIR="/var/www/html/weathermap/graph"
INSTALL_DIR="/home/user1/ss_wm"
USERNAME=weathermap_user
PASSWORD=password123
GROUP=Weathermap-Interfaces
STATSEEKER="10.2.16.81"
URL="http://10.2.16.81/cgi/nimc02?rid=48377&sort=&report=77&group=36471&tfc_fav=&year=&month=&day=&hour=&minute=&duration=&wday_from=&wday_to=&time_from=&time_to=&tz=Australia%2FBrisbane&tfc=&regex=&top_n=400&group_selector=geto"
```

### ss-wm.sh
1. Set the CONFIG_FILE option to the path to your ss-wm.cfg file
2. E.g. ```CONFIG="/home/user1/ss-wm/ss-wm.cfg```

### ss-wm-api.php
1. Set the CONFIG_FILE option to the path to your ss-wm.cfg file
E.g. ```CONFIG="/home/user1/ss-wm/ss-wm.cfg```

### lib/WeatherMapDataSource_statseeker.php
1. Set the CONFIG_FILE option to the path to your ss-wm.cfg file
E.g. ```CONFIG="/home/user1/ss-wm/ss-wm.cfg```

2. Copy the file "lib/WeatherMapDataSource_statseeker.php" to the weathermap data-sources directory.
E.g. `cp lib/WeatherMapDataSource_statseeker.php /var/www/html/weathermap/lib/datasources/`

## Network Weathermap Configuration
The bulk of the weathermap configuration is exactly the same as standard weathermap, you can either edit the config files directly or use the graphical, **http://your.weathermap.server/editor.php**
Once the Statseeker to Weathermap integration is installed, a new **datasource** with the name of **"statseeker"** will be available.

Follow the Network Weathermap documentation with regard to configuring your weathermap via either the GUI, or directly editing the relevant configuration file.

## Link Configuration
A particular syntax must be used when specifying the links between nodes:
**DataSource:Device:ifName:DataType**

Parameter | Description
---| ---
DataSource | Name of the Data Source, must be set to **statseeker**.
Device |	The device name as it appears in Statseeker.
ifName | Interface name of the port. Visible in Statseeker via the **Interface -> Details** report, **Interface Name** column.
DataType |	Must be set to **Bps** (bits per second).
E.g. `statseeker:NewYork-swt1:Gi1/10:Bps`.

##### Editing the Config File
When directly editing the config file, the link between nodes is defined by the **TARGET** line.
##### Using the GUI Editor (editor.php)
The link between nodes refers uses the  **Data Source** field.

#### Bandwidth
Be sure to set the correct interface speed in the **BANDWIDTH** field, e.g. `BANDWIDTH 1000M`
#### Popup Graphs
You can optionally configure popup graphs to be displayed when the mouse hovers over a link between nodes.
**http://weathermap.server.url/GRAPH_DIR/DEVICE.PORT.png**
Parameter | Description
---| ---
http://weathermap.server.url | IP or Hostname of the Weathermap server.
GRAPH_DIR | The path within the Weathermap directory to where the graph will be found. E.g. GRAPH_DIR from *ss-wm.cfg*, without the WEB_DIR prefix.
DEVICE | Statseeker device name.
PORT | Statseeker ifName of the port. Forward-slash characters (*/*) must be replaced with dash characters (*-*), e.g. `Gi1/10` becomes `Gi1-10`.
E.g. `http://10.2.16.77/graph/NewYork-swt1.Gi1-10.png`.
##### Editing the Config File
When directly editing the config file, the link between nodes is defined by the **OVERLIBGRAPH** line.
##### Using the GUI Editor (editor.php)
The link between nodes refers uses the  **'Hover' Graph URL** field.

##### Sample Config File Link Entry
LINK node06633-node08576
	OVERLIBGRAPH http://10.2.16.77/graph/Brisbane-rtr.Gi0-2.png
	TARGET statseeker:Brisbane-rtr:Gi0/2:Bps
	NODES node06633 node08576
	BANDWIDTH 1000M


## Test
While configuring Weathermap, you can run a test to confirm that data/graphs are appearing as expected. 
1. Change to your Weathermap server web directory.
E.g. `# cd /var/www/html/weathermap`
2. Manually run the ss-wm.sh script as the *www-data* user with
`#sudo -u www-data {INSTALL_DIR}/ss-wm.sh`
E.g. `#sudo -u www-data /home/user1/ss-wm/ss-wm.sh`

This script will attempt to:
- access the specified Statseeker group and collect ID's of each member
- use this information to retrieve:
-- link utilization data
-- graphs for mouse-over events and copy these graphs to a temporary directory
- move the retrieved graphs to the specified graph directory on the web-server (e.g. `/var/www/html/weathermap/graph`)
- create the html page for viewing the weathermap, as specified by the settings in *ss-wm.cfg*

Any errors encountered will be displayed in the CLI.

## Crontab
For the map and graphs to stay current by periodically retrieving up-to-date data, an entry must be placed in the weathermap servers crontab. 
- Edit cron with the following command as the root user, where <username> is the user that the web server runs as (typically www-data)
`crontab -u <username> -e`
- Set the contents of the cron to
`*/5 * * * * <path_to_ss-wm.sh> > /dev/null 2>&1`
E.g. `*/5 * * * * home/user1/ss-wm/ss-wm.sh > /dev/null 2>&1`


## Version History


## Authors

Developed by Statseeker.

## License
Copyright (c) 2017 Statseeker

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.



## Acknowledgments

We'd like to acknowledge the effort of those responsible for developing, and maintaining, Network Weathermap.