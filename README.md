# didibreakit - A basic smoke testing library designed for use in CI

## Installation
```
composer require mattrink/didibreakit
```

## Usage
```
./didibreakit tests:run vanilla.yaml
```

## Options
Currently the only optional argument is `--branch branchname`. This is to support branch based testing. If your CI process automatically creates environemnts for your branches this argument can be used to pass the name of the branch to the configuration. The branch can then be used in the `urlPattern` option via the `%branch%` replacement.

## Configuration
Configuration is done via a YAML file that is then passed as the only required argument to the command `./vendor/didibreakit tests:run example.yaml`. You can copy the `example.yaml` for a basic configuration to start from.

Within the `options` section of the config file you can provide some default configuration used for all URLs that are checked.
 - `defaultScheme` Request all URLs using HTTP or HTTPS.
 - `urlPattern` The pattern to match for URLs. `%branch` is optional.
 - `verifySSL` If set to false the SSL certificate for HTTPS will not be verified. Useful is using self-signed SSL certificates.
 - `defaultURLs` Applies these URLs to the config for all hosts defined later.

You can specify the hosts and per-host URLs to check via the `hosts` option in the config. You can specify multiple hosts and URLs to check. The host key will be replaced into the `%host%` placeholder in the `urlPattern`. Along with each URL you also need to specify the expected HTTP status response, if a URL responds withs a status code that does not match the expected one the script will return a non-zero status code.

### Example config
```
options:
  defaultScheme: 'https'
  urlPattern: '%branch%.%host%'
  verifySSL: false
  defaultURLs:
    /: 200

hosts:
  example.com:
    scheme: 'https' # You can use scheme to override the defaultScheme above
    urls:
      /news: 200
      /i-expect-this-to-404: 404
  example2.com:
    urls:
      /deals: 200
  example3.com: # Hosts with no URLs will inherit the defaultURLs from above
  example4.com:
```