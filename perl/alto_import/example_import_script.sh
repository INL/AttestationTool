HOST=yourhost.com
USERNAME=username
PASSWORD=password
DB=EuropeanaNewspapers

mysql -uusername -ppassword -hhostdb -e "drop database $DB; create database $DB"
mysql -uusername -ppassword -hhostdb  $DB < emptyAttestationDatabase.sql
mysql -uusername -ppassword -hhostdb  $DB < types_asis.sql
mysql -uusername -ppassword -hhostdb  $DB -e "insert into revisors (name) VALUES (\"jesse\")"
mysql -uusername -ppassword -hhostdb  $DB -e "insert into revisors (name) VALUES (\"boukje\")"
mysql -uusername -ppassword -hhostdb  $DB -e "insert into revisors (name) VALUES (\"katrien\")"
perl alto2db.pl -h $HOST -u $USERNAME - p $PASSWORD -d $DB [INPUT FILES]
