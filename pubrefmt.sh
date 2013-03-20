#!/bin/sh

sdomains="xxx.com.cn yyy.com.cn";
ddomain=kitech.com.cn

for domain in $sdomains
do
    echo $domain;
    files=$(grep -iR "$domain" *|grep -v pubrefmt.sh|awk -F\: '{print $1}');
    echo $files;
    
    for file in $files
    do
        echo $file;
        set -x
        sed -i "s/$domain/$ddomain/gi" $file
        set +x
    done
done

