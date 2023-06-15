macos-universal: macos-x86_64 macos-arm64
	lipo -create -output process-mysqldump-macos process-mysqldump-macos-x86_64 process-mysqldump-macos-arm64
macos-x86_64: process-mysqldump.c
	gcc -O2 -Wall -pedantic process-mysqldump.c -o process-mysqldump-macos-x86_64 -target x86_64-apple-macos10.12
macos-arm64: process-mysqldump.c
	gcc -O2 -Wall -pedantic process-mysqldump.c -o process-mysqldump-macos-arm64 -target arm64-apple-macos11
ubuntu-amd64:
	DOCKER_BUILDKIT=1 docker build --file Dockerfile-ubuntu-amd64 --tag process-mysqldump-ubuntu-amd64 --output . .
