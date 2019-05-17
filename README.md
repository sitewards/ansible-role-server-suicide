# Ansible Server Suicide Role

This is the Ansible Server Suicide Role. It's designed for consumption by playbooks, not for consumption by itself.
It installs little PHP script and a cron task to check how long the server is running and if it is being used.
If the server is not used for more than defined period of time (4 hours by default), that PHP script sends 
a HTTP request (e.g. to the showroom-hypervisor) or executes a shell command to terminate the machine which 
is running the script. 

Please note that both HTTP call and shell commands have to be provided by other applications or services,
this script only takes the decision about termination and triggers the execution, it does not have any termination
logic and/or corresponding permissions in it.

This solution was originally designed to to be used together with hypervisor, as of now it can be used with 
anything including hypervisor. One use case is as before - just call hypervisor's URL and hypervisor will kill
this instance. Another use case is to execute a shell command, e.g. ("poweroff"). And if instance was configured
to terminate on shutdown - it will cause instance termination.

## Limitations 

## Compatibility

| OS           |
|--------------|
| Ubuntu 16.04 |

Untested on other platforms, but should work for 14.04 to 17.4

## Customisation

A limited number of options are configurable based on variables. For a full list and explanation of veriables, see the
defaults/main.yml file

## Usage

simplies use case:

```
php monitor.php --logfile /var/log/php_errors.log --interval 4hours --termination "shutdown"
```

this will check if machine is running longer than `4 hours` and if last entry in the `/var/log/php_errors.log` file was 
made at least `4 hours` ago, if both are true - "shutdown" command will be executed by the script.
