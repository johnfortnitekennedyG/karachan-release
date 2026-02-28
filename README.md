# Karachan - new imageboard
karachan is my messy little old project from a year ago, a stripped-down, turbo-simplified fork of vichan that's way smaller, way meaner, and tries to fix some of the flaws with vichan/tinyboard to fix issues. i ripped out a ton of ancient bloat, custom permissions, added post approval so you can finally stop bots and illegal material, threw in some basic browser fingerprinting (though easy to defeat, releasing the source here will have the implication of leaking the methods utilized, but whatever) to spot ban evaders and filter spam easier, and sprinkled a few other QoL goodies on top. it's still a chaotic piece of shit however!

I will not be responding to issues or pull requests or any contribution ideas here, this is a public source release. I don't care what you do with it, if you want to make some cancerous soy splinter or whatever that's up to you but I don't really care. Have fun I guess. The codebase is tuned to my own liking, you will have to change a bunch of stuff to make it feel like yours, but otherwise it's alright.

This was used to make the old and new iteration of gemjak.party - a splinter I had something to do with for a while until I didn't.

## Installation (Ubuntu)
```bash
# update and grab basics
sudo apt update
sudo apt install -y ca-certificates curl

# make keyrings dir + add docker gpg key
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

# add the docker repo (this auto-detects your ubuntu version)
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# update again + install everything
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# optional but you probably want this (run docker without sudo)
sudo usermod -aG docker $USER
# then log out + log back in (or new terminal)
```

It's up to you to provide a proxy configuration (for nginx or whatever), you can also build it yourself but I always used docker and the docker method is easier since it only requires running a single command:
```bash
docker compose up --build
```

Then head to 127.0.0.1:8080/install.php and proceed with installation. No other steps required. It will create a default admin account named "Kara" with the password "08uCGvS1bF1tE45v6bPKNH".

## Cleaning docker
docker rm -f $(docker ps -aq)
docker rmi -f $(docker images -q)
docker system prune -a --volumes

## Docker setup
docker compose down -v
docker compose up --build