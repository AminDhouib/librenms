# Notifly

LibreNMS can send alerts by triggering a Notifly workflow for one subscriber.

## Configuration

Create a Notifly alert transport in LibreNMS and provide the following values:

| Config | Example |
| --- | --- |
| API Key | `your-api-key` |
| Workflow | `librenms-alerts` |
| Subscriber ID | `your-subscriber-id` |

## Payload

The transport triggers the workflow with these payload values, which can be
used in the workflow templates, for example `{{payload.title}}`:

| Value | Content |
| --- | --- |
| `title` | Alert title |
| `body` | Alert message |
| `severity` | LibreNMS alert severity |
| `state` | `alert`, `recovered`, `acknowledged`, `worse` or `better` |
| `hostname` | Device hostname |

The workflow trigger identifier and the subscriber ID come from your Notifly
workspace. The API key is created under API Keys.

API documentation: <https://notifly.io/docs/api>
