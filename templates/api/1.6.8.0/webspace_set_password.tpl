<!-- Copyright 1999-2016. Parallels IP Holdings GmbH. -->
<webspace>
    <set>
        <filter>
            <name><?php echo $domain; ?></name>
        </filter>
        <values>
            <hosting>
                <vrt_hst>
                    <property>
                        <name>ftp_password</name>
                        <value><?php echo $password; ?></value>
                    </property>
                </vrt_hst>
            </hosting>
        </values>
    </set>
</webspace>
