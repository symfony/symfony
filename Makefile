QA_DOCKER_IMAGE=jakzal/phpqa:latest
QA_DOCKER_COMMAND=docker run -it --rm -v "$(shell pwd):/project" -w /project ${QA_DOCKER_IMAGE}

phpstan:
	sh -c "${QA_DOCKER_COMMAND} phpstan analyse --configuration phpstan.neon --level 0 src/Symfony/Bridge"

##
# Special operations
##

.PHONY: phpstan
