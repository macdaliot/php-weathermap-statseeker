#!/bin/bash

# This script reads the config file "ss-wm.cfg", then wget's a report and graphs for the popups for weathermap.
# files are left $tmpdir/graph/

# VERSION 0.3.1

CONFIG_FILE=""

if [ ! -e $CONFIG_FILE ]; 
then
	echo "MISSING CONFIG_FILE $CONFIG_FILE"
	exit
fi

typeset -A config # init array
config=( # set default values in config array
    [USERNAME]="user"     # defaults, user/pass are configured in ss-wm.cfg
    [PASSWORD]="pass"
)


# Read the config file
while read line
do
    [[ "$line" =~ ^#.*$ ]] && continue
    if echo $line | grep -F = &>/dev/null
    then
        varname=$(echo "$line" | cut -d '=' -f 1)
        config[$varname]=$(echo "$line" | cut -d '=' -f 2-)
        if [ -z "${config[$varname]}" ] || [ "${config[$varname]}" == "\"\"" ]
        then
           echo "ERROR: $varname not set"
           exit
        fi
    fi
done < ${CONFIG_FILE}


echo "Read config"

#
# Strip the quotes from some parameters as we don't need them here. (PHP does).
#

config[STATSEEKER]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[STATSEEKER]}"`
config[INSTALL_DIR]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[INSTALL_DIR]}"`
config[TMP_DIR]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[TMP_DIR]}"`
config[WEB_DIR]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[WEB_DIR]}"`
config[GRAPH_DIR]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[GRAPH_DIR]}"`
config[WEATHERMAP_BIN]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[WEATHERMAP_BIN]}"` 
config[WEATHERMAP_CONF]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[WEATHERMAP_CONF]}"` 
config[WEATHERMAP_HTML]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[WEATHERMAP_HTML]}"` 
config[WEATHERMAP_IMG]=`sed -e 's/^"//' -e 's/"$//' <<<"${config[WEATHERMAP_IMG]}"` 
config[OGROUP]=${config[GROUP]}    # Original group name
config[GROUP]=`sed -e 's/ /%20/g' <<<"${config[GROUP]}"` 



# Tests for valid config

for i in "${!config[@]}"
do
   if [[ $i =~ "DIR" ]]; then
      if [ ! -e  "${config[$i]}" ] ; then
         echo "Config: Missing $i: ${config[$i]}"
         err=1
      fi
   fi
done

if [ $err ]; then
    exit;
fi


if [[ $1 == "maponly" ]]; then
   echo "Calling Weathermap";
   echo $WMCMD;
   cd ${config[WEB_DIR]}
   WMCMD="${config[WEATHERMAP_BIN]} --config ${config[WEATHERMAP_CONF]} --output=${config[WEATHERMAP_IMG]} --htmloutput ${config[WEATHERMAP_HTML]}"
   $WMCMD
   exit
fi


# Test the API Connection

APITEST=`php ${config[INSTALL_DIR]}/bin/ss-wm-api.php -t`

if [[ $APITEST =~ "AUTHFAIL" ]]; then
    echo "API Auth Fail for user: ${config[USERNAME]}"
    exit;
fi


# Test URL for http/https or incorrect.
HTTPS="http"
if [[ "${config[URL]}" =~ "https" ]]; then
    HTTP="https"
elif [[ "${config[URL]}" =~ "http" ]]; then
    echo ""
else
    echo "BAD URL: ${config[URL]}";
    exit
fi



saveIFS=$IFS
IFS='=&'
parm=(${config[URL]})
IFS=$saveIFS

declare -A cgiopts
for ((i=0; i<${#parm[@]}; i+=2))
do
    cgiopts[${parm[$i]}]=${parm[$i+1]}
done

# Get the group ID via the php script.

GROUPID=`php ${config["INSTALL_DIR"]}/bin/ss-wm-api.php -g`
echo "GROUPID: $GROUPID";


if [[ $GROUPID == 0 ]];
then
	echo "GROUP ID NOT FOUND FOR: ${config[OGROUP]}";
	exit;
else
	echo "GOT GROUPID: $GROUPID";
fi


#
# Build short url, since the long url makes a file name that's too long.
#

shorturl="$HTTPS://${config[STATSEEKER]}/cgi/nimc02?report=${cgiopts['report']}&group=$GROUPID&tfc_fav=${cgiopts['tfc_fav']}&tz=${cgiopts['tz']}&tfc=${cgiopts['tfc']}&top_n=${cgiopts['top_n']}"

config[URL]=${shorturl}

echo "WGET TopN Report"
#
# Get the report / images.
#

echo ${config[URL]} | wget  --recursive --no-check-certificate   -i - --user=${config[USERNAME]} --password=${config[PASSWORD]} -P ${config[TMP_DIR]} -nd -q

#
# Rename the files to something we can use and leave them in tmpdir/graph
#

echo "Processing graphs"
mkdir -p ${config[TMP_DIR]}/graph/
for i in ${config[TMP_DIR]}/util*.png ; do mv "$i" "${config[TMP_DIR]}/graph/`echo $i | awk -F\. '{ print $3"."$4"."$6 }'`"; done

php ${config[INSTALL_DIR]}/bin/ss-wm-api.php -r


WMCMD="${config[WEATHERMAP_BIN]} --config ${config[WEATHERMAP_CONF]} --output=${config[WEATHERMAP_IMG]} --htmloutput ${config[WEATHERMAP_HTML]}"

echo "Calling Weathermap";
echo $WMCMD;
cd ${config[WEB_DIR]}
$WMCMD

# If we're on secure linux, fix the files.
if command -v restorecon >/dev/null 2>&1; then
   echo "Calling restorecon"
   restorecon ${config[GRAPH_DIR]}/*
   restorecon ${config[WEB_DIR]}/${config[WEATHERMAP_IMG]} 
fi

#
# cleanup the junk files from the wget that we don't need.
#
find ${config[TMP_DIR]} -maxdepth 1 -type f  -exec rm {} \;
