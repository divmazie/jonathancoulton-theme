# Docker images for testing

You need to symlink or create your own docker-compose.yml
based on your needs.


## Wordpress

Create directories for wordpress stuff so you can look at it and
so it's cached.

You need to set good perms on the wp-content directory. In the spirit
of eff it while developing (that's all it's for... seriously) set
them to to 777


     mkdir -p docker/wordpress/wp-content/
     mkdir -p docker/wordpress/wp-content/uploads
     chmod -R 777 docker/wordpress/wp-content/


You may need to run this a few times... it sucks but whatever.

##  Logs
Getting bash in db, then seeing the full query logs

    docker exec -it docker_db_1 bash
    echo "SET GLOBAL general_log_file = '/var/log/mysql.log'; SET GLOBAL general_log = 'ON';" | mysql -uroot -p1234; tail -f /var/log/mysql.log

Getting bash in wp

    docker exec -it docker_wordpress_1 bash
