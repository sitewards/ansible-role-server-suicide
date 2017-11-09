# Ansible Server Suicide Role

This is the Ansible Server Suicide Role. It's designed for consumption by playbooks, not for consumption by itself.
It installs little PHP script and a cron task to check how long the server is running and if it is being used.
IF there server is not used for more than defined period of time (4h by default), that PHP script sends a request 
to the showroom-hypervisor to terminate the machine which is running the script.

This solution can only be used together with hypervisor, it is mostly useless alone, since the termination task 
is perormed by hypervisor, the script here is only responsible for monitoring and deciding on terminatino.

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

this is to be defined