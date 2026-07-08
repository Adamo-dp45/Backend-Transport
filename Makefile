.PHONY: install # Pour indiquer que 'install' ne génère pas de dossier et que c'est une fausse recette, 'deploy'

# deploy: -- Si on a une connexion ssh
#	ssh .. 'cd sites/sf.koalapp.com && git pull origin main && make install'

install: # vendor/autoload.php .. '-n' pour qu'il fait de manière silencieuse
	git pull origin main
	composer install --no-dev --optimize-autoloader
	php bin/console doctrine:migrations:migrate --no-interaction
	php bin/console cache:clear --env=prod
	php bin/console cache:warmup --env=prod

# vendor/autoload.php: composer.lock composer.json -- On peut faire en sorte qu'une recette ne soit pas exécuté tout le temps, on lui explique comment généré 'autoload.php' en lui disant qu'on dépend des fichiers composer.. ce qui fait qu'il va exécuter les commandes qu'on vas lui donné que si le fichier 'autoload.php' est moins récent que l'un des fichiers 'compo..' puis on 'touch' pour mettre à jour 'autoload.php' pour avoir une date de mise à jour récente 
#	composer install --no-dev --optimize-autoloader
#	touch vendor/autoload.php

# Pour le lancer 'make install' va lancer aussi 'vendor/autoload.php', si on a une connexion ssh 'make deploy' qui indique qu'il doit se connecter à ssh puis exécute 'cd..'