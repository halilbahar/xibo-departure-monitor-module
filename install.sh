#!/bin/bash

#Replace key placeholder in the code with the actual key
wienerLinienKey=xxxxxxxxxx
sed -i "s/<Key für Wiener Linien>/$wienerLinienKey/g" ./DepartureMonitor/DepartureMonitor.php

cp -r ./DepartureMonitor/ ../
cp ./departuremonitor.json ../