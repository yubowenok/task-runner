create table if not exists taxi (name varchar(15), val int);
delete from taxi;
load data local infile 'tasks/all-taxes/taxi.csv' into table taxi;
