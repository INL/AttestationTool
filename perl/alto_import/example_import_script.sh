HOST=yourhost.com
USERNAME=username
PASSWORD=password
DB=EuropeanaNewspapers

mysql -uimpact -pimpact -himpactdb -e "drop database $DB; create database $DB"
mysql -uimpact -pimpact -himpactdb  $DB < emptyAttestationDatabase.sql
mysql -uimpact -pimpact -himpactdb  $DB < types_asis.sql
mysql -uimpact -pimpact -himpactdb  $DB -e "insert into revisors (name) VALUES (\"jesse\")"
mysql -uimpact -pimpact -himpactdb  $DB -e "insert into revisors (name) VALUES (\"boukje\")"
mysql -uimpact -pimpact -himpactdb  $DB -e "insert into revisors (name) VALUES (\"katrien\")"
perl alto2db.pl -h $HOST -u $USERNAME - p $PASSWORD -d $DB [INPUT FILES]
