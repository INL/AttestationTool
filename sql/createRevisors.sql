drop table if exists revisors;

CREATE TABLE revisors (
  id int(11) NOT NULL auto_increment,
  name varchar(20) NOT NULL,
  PRIMARY KEY  (`id`)
);

INSERT INTO revisors (name) VALUES ('Impact'), ('Katrien'), ('Hans'), ('Dirk'), ('Tom'), ('Marjolijn'), ('Jesse'), ('Wil'), ('Adrienne'), ('Boukje');
