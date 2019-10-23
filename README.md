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

### Parsing log file with the tool

```
php monitor.php --logfile /var/log/php_errors.log --interval 4hours --termination "/sbin/shutdown -h now"
```

this will check if machine is running longer than `4 hours` and if last entry in the `/var/log/php_errors.log` file was 
made at least `4 hours` ago, if both are true - "shutdown" command will be executed by the script.

### Parsing log file with external tool and using pipe

it is also possible to parse log file manually and feed it in the script:
```
grep nginx /var/log/syslog | grep -v "health_check" | tail -1  | php monitor.php --interval 20minutes --termination "/sbin/shutdown -h now"
```

PLEASE NOTE: only the last line of STDIN will only be processed if no other input is given by `--logfile` or `--syslog`

### Parsing Syslog
For phrasing the syslog and check for log entries from a specific unit, you can use the following:
```
php monitor.php --termination "/sbin/shutdown -h now" --syslog "nginx.service" --interval "4hours"
```
or in your ansible config
```
sitewards_server_suicide_logfile_path: ""
sitewards_server_suicide_syslog_unit: "nginx.service"
```


### Parsing log file with external tool, using pipe and terminating the self with AWS

When on AWS, make sure the following or similar policy is attached to the instance via an IAM role:

```
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor0",
            "Effect": "Allow",
            "Action": [
                "ec2:TerminateInstances",
                "ec2:DescribeInstances"
            ],
            "Resource": "*",
            "Condition": {
                "StringEquals": {
                    "aws:ARN": "${ec2:SourceInstanceARN}"
                }
            }
        }
    ]
}
```

then you can do something like:

```
grep nginx /var/log/syslog | grep -v "health_check" | tail -1  | php monitor.php --interval 20minutes --termination "aws ec2 terminate-instances --instance-ids $(curl http://169.254.169.254/latest/meta-data/instance-id/ 2>/dev/null) --region $(curl http://169.254.169.254/latest/meta-data/placement/availability-zone 2>/dev/null | sed 's/.$//')"
```
