[Authentication](#authentication)

[`search` - Search models, collections, and docs (beta)](#search)

[`predictions.create` - Create a prediction](#predictions.create)

[`predictions.get` - Get a prediction](#predictions.get)

[`predictions.list` - List predictions](#predictions.list)

[`predictions.cancel` - Cancel a prediction](#predictions.cancel)

[`models.create` - Create a model](#models.create)

[`models.get` - Get a model](#models.get)

[`models.list` - List public models](#models.list)

[`models.update` - Update metadata for a model](#models.update)

[`models.search` - Search public models](#models.search)

[`models.delete` - Delete a model](#models.delete)

[`models.examples.list` - List examples for a model](#models.examples.list)

[`models.predictions.create` - Create a prediction using an official model](#models.predictions.create)

[`models.readme.get` - Get a model's README](#models.readme.get)

[`models.versions.get` - Get a model version](#models.versions.get)

[`models.versions.list` - List model versions](#models.versions.list)

[`models.versions.delete` - Delete a model version](#models.versions.delete)

[`collections.get` - Get a collection of models](#collections.get)

[`collections.list` - List collections of models](#collections.list)

[`deployments.create` - Create a deployment](#deployments.create)

[`deployments.get` - Get a deployment](#deployments.get)

[`deployments.list` - List deployments](#deployments.list)

[`deployments.update` - Update a deployment](#deployments.update)

[`deployments.delete` - Delete a deployment](#deployments.delete)

[`deployments.predictions.create` - Create a prediction using a deployment](#deployments.predictions.create)

[`files.list` - List files](#files.list)

[`files.create` - Create a file](#files.create)

[`files.delete` - Delete a file](#files.delete)

[`files.get` - Get a file](#files.get)

[`files.download` - Download a file](#files.download)

[`trainings.create` - Create a training](#trainings.create)

[`trainings.get` - Get a training](#trainings.get)

[`trainings.list` - List trainings](#trainings.list)

[`trainings.cancel` - Cancel a training](#trainings.cancel)

[`hardware.list` - List available hardware for models](#hardware.list)

[`account.get` - Get the authenticated account](#account.get)

[`webhooks.default.secret.get` - Get the signing secret for the default webhook](#webhooks.default.secret.get)

[](#authentication)Authentication
---------------------------------

All API requests must include a valid API token in the `Authorization` request header. The token must be prefixed by “Bearer”, followed by a space and the token value. Example: `Authorization: Bearer r8_Hw***********************************` Find your tokens at [https://replicate.com/account/api-tokens](https://replicate.com/account/api-tokens)

[](#search)Search models, collections, and docs (beta)
------------------------------------------------------

### [](#search-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/search
```

### [](#search-description)Description

Search for public models, collections, and docs using a text query.

For models, the response includes all model data, plus a new `metadata` object with the following fields:

*   `generated_description`: A longer and more detailed AI-generated description of the model
*   `tags`: An array of tags for the model
*   `score`: A score for the model’s relevance to the search query

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  "https://api.replicate.com/v1/search?query=nano+banana"
```

Note: This search API is currently in beta and may change in future versions.

### [](#search-query-parameters)Query Parameters

*   [query](#search-query-parameters-query)stringRequired
    
    The search query string
    
*   [limit](#search-query-parameters-limit)integer
    
    Maximum number of model results to return (1-50, defaults to 20)
    

[](#predictions.create)Create a prediction
------------------------------------------

### [](#predictions.create-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/predictions
```

### [](#predictions.create-description)Description

Create a prediction for the model version and inputs you provide.

Example cURL request:

Copy

```shell
curl -s -X POST -H 'Prefer: wait' \
  -d '{"version": "replicate/hello-world:5c7d5dc6dd8bf75c1acaa8565735e7986bc5b66206b55cca93cb72c9bf15ccaa", "input": {"text": "Alice"}}' \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H 'Content-Type: application/json' \
  https://api.replicate.com/v1/predictions
```

The request will wait up to 60 seconds for the model to run. If this time is exceeded the prediction will be returned in a `"starting"` state and need to be retrieved using the `predictions.get` endpoint.

For a complete overview of the `predictions.create` API check out our documentation on [creating a prediction](https://replicate.com/docs/topics/predictions/create-a-prediction) which covers a variety of use cases.

### [](#predictions.create-headers)Headers

*   [Prefer](#predictions.create-headers-prefer)string
    
    Leave the request open and wait for the model to finish generating output. Set to `wait=n` where n is a number of seconds between 1 and 60.
    
    See https://replicate.com/docs/topics/predictions/create-a-prediction#sync-mode for more information.
    

### [](#predictions.create-request-body)Request Body

*   [input](#predictions.create-request-body-input)objectRequired
    
    The model's input as a JSON object. The input schema depends on what model you are running. To see the available inputs, click the "API" tab on the model you are running or [get the model version](#models.versions.get) and look at its `openapi_schema` property. For example, [stability-ai/sdxl](https://replicate.com/stability-ai/sdxl) takes `prompt` as an input.
    
    Files should be passed as HTTP URLs or data URLs.
    
    Use an HTTP URL when:
    
    *   you have a large file > 256kb
    *   you want to be able to use the file multiple times
    *   you want your prediction metadata to be associable with your input files
    
    Use a data URL when:
    
    *   you have a small file <= 256kb
    *   you don't want to upload and host the file somewhere
    *   you don't need to use the file again (Replicate will not store it)
    
*   [version](#predictions.create-request-body-version)stringRequired
    
    The identifier for the model or model version that you want to run. This can be specified in a few different formats:
    
    *   `{owner_name}/{model_name}` - Use this format for [official models](https://replicate.com/docs/topics/models/official-models). For example, `black-forest-labs/flux-schnell`. For all other models, the specific version is required.
    *   `{owner_name}/{model_name}:{version_id}` - The owner and model name, plus the full 64-character version ID. For example, `replicate/hello-world:9dcd6d78e7c6560c340d916fe32e9f24aabfa331e5cce95fe31f77fb03121426`.
    *   `{version_id}` - Just the 64-character version ID. For example, `9dcd6d78e7c6560c340d916fe32e9f24aabfa331e5cce95fe31f77fb03121426`
    
*   [stream](#predictions.create-request-body-stream)boolean
    
    **This field is deprecated.**
    
    Request a URL to receive streaming output using [server-sent events (SSE)](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events).
    
    This field is no longer needed as the returned prediction will always have a `stream` entry in its `urls` property if the model supports streaming.
    
*   [webhook](#predictions.create-request-body-webhook)string
    
    An HTTPS URL for receiving a webhook when the prediction has new output. The webhook will be a POST request where the request body is the same as the response body of the [get prediction](#predictions.get) operation. If there are network problems, we will retry the webhook a few times, so make sure it can be safely called more than once. Replicate will not follow redirects when sending webhook requests to your service, so be sure to specify a URL that will resolve without redirecting.
    
*   [webhook\_events\_filter](#predictions.create-request-body-webhookeventsfilter)array
    
    By default, we will send requests to your webhook URL whenever there are new outputs or the prediction has finished. You can change which events trigger webhook requests by specifying `webhook_events_filter` in the prediction request:
    
    *   `start`: immediately on prediction start
    *   `output`: each time a prediction generates an output (note that predictions can generate multiple outputs)
    *   `logs`: each time log output is generated by a prediction
    *   `completed`: when the prediction reaches a terminal state (succeeded/canceled/failed)
    
    For example, if you only wanted requests to be sent at the start and end of the prediction, you would provide:
    
    {
      "version": "5c7d5dc6dd8bf75c1acaa8565735e7986bc5b66206b55cca93cb72c9bf15ccaa",
      "input": {
        "text": "Alice"
      },
      "webhook": "https://example.com/my-webhook",
      "webhook_events_filter": ["start", "completed"]
    }
    
    Requests for event types `output` and `logs` will be sent at most once every 500ms. If you request `start` and `completed` webhooks, then they'll always be sent regardless of throttling.
    

[](#predictions.get)Get a prediction
------------------------------------

### [](#predictions.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/predictions/{prediction_id}
```

### [](#predictions.get-description)Description

Get the current state of a prediction.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/predictions/gm3qorzdhgbfurvjtvhg6dckhu
```

The response will be the prediction object:

Copy

```json
{
  "id": "gm3qorzdhgbfurvjtvhg6dckhu",
  "model": "replicate/hello-world",
  "version": "5c7d5dc6dd8bf75c1acaa8565735e7986bc5b66206b55cca93cb72c9bf15ccaa",
  "input": {
    "text": "Alice"
  },
  "logs": "",
  "output": "hello Alice",
  "error": null,
  "status": "succeeded",
  "created_at": "2023-09-08T16:19:34.765994Z",
  "data_removed": false,
  "started_at": "2023-09-08T16:19:34.779176Z",
  "completed_at": "2023-09-08T16:19:34.791859Z",
  "metrics": {
    "predict_time": 0.012683
  },
  "urls": {
    "web": "https://replicate.com/p/gm3qorzdhgbfurvjtvhg6dckhu",
    "get": "https://api.replicate.com/v1/predictions/gm3qorzdhgbfurvjtvhg6dckhu",
    "cancel": "https://api.replicate.com/v1/predictions/gm3qorzdhgbfurvjtvhg6dckhu/cancel"
  }
}
```

`status` will be one of:

*   `starting`: the prediction is starting up. If this status lasts longer than a few seconds, then it’s typically because a new worker is being started to run the prediction.
*   `processing`: the `predict()` method of the model is currently running.
*   `succeeded`: the prediction completed successfully.
*   `failed`: the prediction encountered an error during processing.
*   `canceled`: the prediction was canceled by its creator.

In the case of success, `output` will be an object containing the output of the model. Any files will be represented as HTTPS URLs. You’ll need to pass the `Authorization` header to request them.

In the case of failure, `error` will contain the error encountered during the prediction.

Terminated predictions (with a status of `succeeded`, `failed`, or `canceled`) will include a `metrics` object with a `predict_time` property showing the amount of CPU or GPU time, in seconds, that the prediction used while running. It won’t include time waiting for the prediction to start. The `metrics` object will also include a `total_time` property showing the total time, in seconds, that the prediction took to complete.

All input parameters, output values, and logs are automatically removed after an hour, by default, for predictions created through the API.

You must save a copy of any data or files in the output if you’d like to continue using them. The `output` key will still be present, but it’s value will be `null` after the output has been removed.

Output files are served by `replicate.delivery` and its subdomains. If you use an allow list of external domains for your assets, add `replicate.delivery` and `*.replicate.delivery` to it.

### [](#predictions.get-parameters)URL Parameters

*   [prediction\_id](#predictions.get-parameters-predictionid)stringRequired
    
    The ID of the prediction to get.
    

[](#predictions.list)List predictions
-------------------------------------

### [](#predictions.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/predictions
```

### [](#predictions.list-description)Description

Get a paginated list of all predictions created by the user or organization associated with the provided API token.

This will include predictions created from the API and the website. It will return 100 records per page.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/predictions
```

The response will be a paginated JSON array of prediction objects, sorted with the most recent prediction first:

Copy

```json
{
  "next": null,
  "previous": null,
  "results": [
    {
      "completed_at": "2023-09-08T16:19:34.791859Z",
      "created_at": "2023-09-08T16:19:34.907244Z",
      "data_removed": false,
      "error": null,
      "id": "gm3qorzdhgbfurvjtvhg6dckhu",
      "input": {
        "text": "Alice"
      },
      "metrics": {
        "predict_time": 0.012683
      },
      "output": "hello Alice",
      "started_at": "2023-09-08T16:19:34.779176Z",
      "source": "api",
      "status": "succeeded",
      "urls": {
        "web": "https://replicate.com/p/gm3qorzdhgbfurvjtvhg6dckhu",
        "get": "https://api.replicate.com/v1/predictions/gm3qorzdhgbfurvjtvhg6dckhu",
        "cancel": "https://api.replicate.com/v1/predictions/gm3qorzdhgbfurvjtvhg6dckhu/cancel"
      },
      "model": "replicate/hello-world",
      "version": "5c7d5dc6dd8bf75c1acaa8565735e7986bc5b66206b55cca93cb72c9bf15ccaa",
    }
  ]
}
```

`id` will be the unique ID of the prediction.

`source` will indicate how the prediction was created. Possible values are `web` or `api`.

`status` will be the status of the prediction. Refer to [get a single prediction](#predictions.get) for possible values.

`urls` will be a convenience object that can be used to construct new API requests for the given prediction. If the requested model version supports streaming, this will have a `stream` entry with an HTTPS URL that you can use to construct an [`EventSource`](https://developer.mozilla.org/en-US/docs/Web/API/EventSource).

`model` will be the model identifier string in the format of `{model_owner}/{model_name}`.

`version` will be the unique ID of model version used to create the prediction.

`data_removed` will be `true` if the input and output data has been deleted.

### [](#predictions.list-query-parameters)Query Parameters

*   [created\_after](#predictions.list-query-parameters-createdafter)string
    
    Include only predictions created at or after this date-time, in ISO 8601 format.
    
*   [created\_before](#predictions.list-query-parameters-createdbefore)string
    
    Include only predictions created before this date-time, in ISO 8601 format.
    

[](#predictions.cancel)Cancel a prediction
------------------------------------------

### [](#predictions.cancel-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/predictions/{prediction_id}/cancel
```

### [](#predictions.cancel-description)Description

Cancel a prediction that is currently running.

Example cURL request that creates a prediction and then cancels it:

Copy

```shell
# First, create a prediction
PREDICTION_ID=$(curl -s -X POST \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "input": {
      "prompt": "a video that may take a while to generate"
    }
  }' \
  https://api.replicate.com/v1/models/minimax/video-01/predictions | jq -r '.id')
# Echo the prediction ID
echo "Created prediction with ID: $PREDICTION_ID"
# Cancel the prediction
curl -s -X POST \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/predictions/$PREDICTION_ID/cancel
```

### [](#predictions.cancel-parameters)URL Parameters

*   [prediction\_id](#predictions.cancel-parameters-predictionid)stringRequired
    
    The ID of the prediction to cancel.
    

[](#models.create)Create a model
--------------------------------

### [](#models.create-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/models
```

### [](#models.create-description)Description

Create a model.

Example cURL request:

Copy

```shell
curl -s -X POST \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"owner": "alice", "name": "hot-dog-detector", "description": "Detect hot dogs in images", "visibility": "public", "hardware": "cpu"}' \
  https://api.replicate.com/v1/models
```

The response will be a model object in the following format:

Copy

```json
{
  "url": "https://replicate.com/alice/hot-dog-detector",
  "owner": "alice",
  "name": "hot-dog-detector",
  "description": "Detect hot dogs in images",
  "visibility": "public",
  "github_url": null,
  "paper_url": null,
  "license_url": null,
  "run_count": 0,
  "cover_image_url": null,
  "default_example": null,
  "latest_version": null,
}
```

Note that there is a limit of 1,000 models per account. For most purposes, we recommend using a single model and pushing new [versions](https://replicate.com/docs/how-does-replicate-work#versions) of the model as you make changes to it.

### [](#models.create-request-body)Request Body

*   [hardware](#models.create-request-body-hardware)stringRequired
    
    The SKU for the hardware used to run the model. Possible values can be retrieved from the `hardware.list` endpoint.
    
*   [name](#models.create-request-body-name)stringRequired
    
    The name of the model. This must be unique among all models owned by the user or organization.
    
*   [owner](#models.create-request-body-owner)stringRequired
    
    The name of the user or organization that will own the model. This must be the same as the user or organization that is making the API request. In other words, the API token used in the request must belong to this user or organization.
    
*   [visibility](#models.create-request-body-visibility)stringRequired
    
    Whether the model should be public or private. A public model can be viewed and run by anyone, whereas a private model can be viewed and run only by the user or organization members that own the model.
    
*   [cover\_image\_url](#models.create-request-body-coverimageurl)string
    
    A URL for the model's cover image. This should be an image file.
    
*   [description](#models.create-request-body-description)string
    
    A description of the model.
    
*   [github\_url](#models.create-request-body-githuburl)string
    
    A URL for the model's source code on GitHub.
    
*   [license\_url](#models.create-request-body-licenseurl)string
    
    A URL for the model's license.
    
*   [paper\_url](#models.create-request-body-paperurl)string
    
    A URL for the model's paper.
    

[](#models.get)Get a model
--------------------------

### [](#models.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/models/{model_owner}/{model_name}
```

### [](#models.get-description)Description

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/models/replicate/hello-world
```

The response will be a model object in the following format:

Copy

```json
{
  "url": "https://replicate.com/replicate/hello-world",
  "owner": "replicate",
  "name": "hello-world",
  "description": "A tiny model that says hello",
  "visibility": "public",
  "github_url": "https://github.com/replicate/cog-examples",
  "paper_url": null,
  "license_url": null,
  "run_count": 5681081,
  "cover_image_url": "...",
  "default_example": {...},
  "latest_version": {...},
}
```

The model object includes the [input and output schema](https://replicate.com/docs/reference/openapi#model-schemas) for the latest version of the model.

Here’s an example showing how to fetch the model with cURL and display its input schema with [jq](https://stedolan.github.io/jq/):

Copy

```shell
curl -s \
    -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
    https://api.replicate.com/v1/models/replicate/hello-world \
    | jq ".latest_version.openapi_schema.components.schemas.Input"
```

This will return the following JSON object:

Copy

```json
{
  "type": "object",
  "title": "Input",
  "required": [
    "text"
  ],
  "properties": {
    "text": {
      "type": "string",
      "title": "Text",
      "x-order": 0,
      "description": "Text to prefix with 'hello '"
    }
  }
}
```

The `cover_image_url` string is an HTTPS URL for an image file. This can be:

*   An image uploaded by the model author.
*   The output file of the example prediction, if the model author has not set a cover image.
*   The input file of the example prediction, if the model author has not set a cover image and the example prediction has no output file.
*   A generic fallback image.

The `default_example` object is a [prediction](#predictions.get) created with this model.

The `latest_version` object is the model’s most recently pushed [version](#models.versions.get).

### [](#models.get-parameters)URL Parameters

*   [model\_owner](#models.get-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.get-parameters-modelname)stringRequired
    
    The name of the model.
    

[](#models.list)List public models
----------------------------------

### [](#models.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/models
```

### [](#models.list-description)Description

Get a paginated list of public models.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/models
```

The response will be a pagination object containing a list of model objects.

See the [`models.get`](#models.get) docs for more details about the model object.

[](#models.update)Update metadata for a model
---------------------------------------------

### [](#models.update-endpoint)Endpoint

Copy

```plaintext
PATCH https://api.replicate.com/v1/models/{model_owner}/{model_name}
```

### [](#models.update-description)Description

Update select properties of an existing model.

You can update the following properties:

*   `description` - Model description
*   `readme` - Model README content
*   `github_url` - GitHub repository URL
*   `paper_url` - Research paper URL
*   `weights_url` - Model weights URL
*   `license_url` - License URL

Example cURL request:

Copy

```shell
curl -X PATCH \
  https://api.replicate.com/v1/models/your-username/your-model-name \
  -H "Authorization: Token $REPLICATE_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "description": "Detect hot dogs in images",
    "readme": "# Hot Dog Detector\n\n Ketchup, mustard, and onions...",
    "github_url": "https://github.com/alice/hot-dog-detector",
    "paper_url": "https://arxiv.org/abs/2504.17639",
    "weights_url": "https://huggingface.co/alice/hot-dog-detector",
    "license_url": "https://choosealicense.com/licenses/mit/"
  }'
```

The response will be the updated model object with all of its properties.

### [](#models.update-parameters)URL Parameters

*   [model\_owner](#models.update-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.update-parameters-modelname)stringRequired
    
    The name of the model.
    

### [](#models.update-request-body)Request Body

*   [description](#models.update-request-body-description)string
    
    A description of the model.
    
*   [github\_url](#models.update-request-body-githuburl)string
    
    A URL for the model's source code on GitHub.
    
*   [license\_url](#models.update-request-body-licenseurl)string
    
    A URL for the model's license.
    
*   [paper\_url](#models.update-request-body-paperurl)string
    
    A URL for the model's paper.
    
*   [readme](#models.update-request-body-readme)string
    
    The README content of the model.
    
*   [weights\_url](#models.update-request-body-weightsurl)string
    
    A URL for the model's weights.
    

[](#models.search)Search public models
--------------------------------------

### [](#models.search-endpoint)Endpoint

Copy

```plaintext
QUERY https://api.replicate.com/v1/models
```

### [](#models.search-description)Description

Get a list of public models matching a search query.

Example cURL request:

Copy

```shell
curl -s -X QUERY \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H "Content-Type: text/plain" \
  -d "hello" \
  https://api.replicate.com/v1/models
```

The response will be a paginated JSON object containing an array of model objects.

See the [`models.get`](#models.get) docs for more details about the model object.

[](#models.delete)Delete a model
--------------------------------

### [](#models.delete-endpoint)Endpoint

Copy

```plaintext
DELETE https://api.replicate.com/v1/models/{model_owner}/{model_name}
```

### [](#models.delete-description)Description

Delete a model

Model deletion has some restrictions:

*   You can only delete models you own.
*   You can only delete private models.
*   You can only delete models that have no versions associated with them. Currently you’ll need to [delete the model’s versions](#models.versions.delete) before you can delete the model itself.

Example cURL request:

Copy

```shell
curl -s -X DELETE \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/models/replicate/hello-world
```

The response will be an empty 204, indicating the model has been deleted.

### [](#models.delete-parameters)URL Parameters

*   [model\_owner](#models.delete-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.delete-parameters-modelname)stringRequired
    
    The name of the model.
    

[](#models.examples.list)List examples for a model
--------------------------------------------------

### [](#models.examples.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/models/{model_owner}/{model_name}/examples
```

### [](#models.examples.list-description)Description

List [example predictions](https://replicate.com/docs/topics/models/publish-a-model#what-are-examples) made using the model. These are predictions that were saved by the model author as illustrative examples of the model’s capabilities.

If you want all the examples for a model, use this operation.

If you just want the model’s default example, you can use the [`models.get`](#models.get) operation instead, which includes a `default_example` object.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/models/replicate/hello-world/examples
```

The response will be a pagination object containing a list of example predictions:

Copy

```json
{
  "next": "https://api.replicate.com/v1/models/replicate/hello-world/examples?cursor=...",
  "previous": "https://api.replicate.com/v1/models/replicate/hello-world/examples?cursor=...",
  "results": [...]
}
```

Each item in the `results` list is a [prediction object](#predictions.get).

### [](#models.examples.list-parameters)URL Parameters

*   [model\_owner](#models.examples.list-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.examples.list-parameters-modelname)stringRequired
    
    The name of the model.
    

[](#models.predictions.create)Create a prediction using an official model
-------------------------------------------------------------------------

### [](#models.predictions.create-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/models/{model_owner}/{model_name}/predictions
```

### [](#models.predictions.create-description)Description

Create a prediction using an [official model](https://replicate.com/changelog/2025-01-29-official-models).

If you’re _not_ running an official model, use the [`predictions.create`](#predictions.create) operation instead.

Example cURL request:

Copy

```shell
curl -s -X POST -H 'Prefer: wait' \
  -d '{"input": {"prompt": "Write a short poem about the weather."}}' \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H 'Content-Type: application/json' \
  https://api.replicate.com/v1/models/meta/meta-llama-3-70b-instruct/predictions
```

The request will wait up to 60 seconds for the model to run. If this time is exceeded the prediction will be returned in a `"starting"` state and need to be retrieved using the `predictions.get` endpoint.

For a complete overview of the `deployments.predictions.create` API check out our documentation on [creating a prediction](https://replicate.com/docs/topics/predictions/create-a-prediction) which covers a variety of use cases.

### [](#models.predictions.create-parameters)URL Parameters

*   [model\_owner](#models.predictions.create-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.predictions.create-parameters-modelname)stringRequired
    
    The name of the model.
    

### [](#models.predictions.create-headers)Headers

*   [Prefer](#models.predictions.create-headers-prefer)string
    
    Leave the request open and wait for the model to finish generating output. Set to `wait=n` where n is a number of seconds between 1 and 60.
    
    See https://replicate.com/docs/topics/predictions/create-a-prediction#sync-mode for more information.
    

### [](#models.predictions.create-request-body)Request Body

*   [input](#models.predictions.create-request-body-input)objectRequired
    
    The model's input as a JSON object. The input schema depends on what model you are running. To see the available inputs, click the "API" tab on the model you are running or [get the model version](#models.versions.get) and look at its `openapi_schema` property. For example, [stability-ai/sdxl](https://replicate.com/stability-ai/sdxl) takes `prompt` as an input.
    
    Files should be passed as HTTP URLs or data URLs.
    
    Use an HTTP URL when:
    
    *   you have a large file > 256kb
    *   you want to be able to use the file multiple times
    *   you want your prediction metadata to be associable with your input files
    
    Use a data URL when:
    
    *   you have a small file <= 256kb
    *   you don't want to upload and host the file somewhere
    *   you don't need to use the file again (Replicate will not store it)
    
*   [stream](#models.predictions.create-request-body-stream)boolean
    
    **This field is deprecated.**
    
    Request a URL to receive streaming output using [server-sent events (SSE)](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events).
    
    This field is no longer needed as the returned prediction will always have a `stream` entry in its `urls` property if the model supports streaming.
    
*   [webhook](#models.predictions.create-request-body-webhook)string
    
    An HTTPS URL for receiving a webhook when the prediction has new output. The webhook will be a POST request where the request body is the same as the response body of the [get prediction](#predictions.get) operation. If there are network problems, we will retry the webhook a few times, so make sure it can be safely called more than once. Replicate will not follow redirects when sending webhook requests to your service, so be sure to specify a URL that will resolve without redirecting.
    
*   [webhook\_events\_filter](#models.predictions.create-request-body-webhookeventsfilter)array
    
    By default, we will send requests to your webhook URL whenever there are new outputs or the prediction has finished. You can change which events trigger webhook requests by specifying `webhook_events_filter` in the prediction request:
    
    *   `start`: immediately on prediction start
    *   `output`: each time a prediction generates an output (note that predictions can generate multiple outputs)
    *   `logs`: each time log output is generated by a prediction
    *   `completed`: when the prediction reaches a terminal state (succeeded/canceled/failed)
    
    For example, if you only wanted requests to be sent at the start and end of the prediction, you would provide:
    
    {
      "input": {
        "text": "Alice"
      },
      "webhook": "https://example.com/my-webhook",
      "webhook_events_filter": ["start", "completed"]
    }
    
    Requests for event types `output` and `logs` will be sent at most once every 500ms. If you request `start` and `completed` webhooks, then they'll always be sent regardless of throttling.
    

[](#models.readme.get)Get a model’s README
------------------------------------------

### [](#models.readme.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/models/{model_owner}/{model_name}/readme
```

### [](#models.readme.get-description)Description

Get the README content for a model.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/models/replicate/hello-world/readme
```

The response will be the README content as plain text in Markdown format:

Copy

```plaintext
# Hello World Model
This is an example model that...
```

### [](#models.readme.get-parameters)URL Parameters

*   [model\_owner](#models.readme.get-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.readme.get-parameters-modelname)stringRequired
    
    The name of the model.
    

[](#models.versions.get)Get a model version
-------------------------------------------

### [](#models.versions.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/models/{model_owner}/{model_name}/versions/{version_id}
```

### [](#models.versions.get-description)Description

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/models/replicate/hello-world/versions/5c7d5dc6dd8bf75c1acaa8565735e7986bc5b66206b55cca93cb72c9bf15ccaa
```

The response will be the version object:

Copy

```json
{
  "id": "5c7d5dc6dd8bf75c1acaa8565735e7986bc5b66206b55cca93cb72c9bf15ccaa",
  "created_at": "2022-04-26T19:29:04.418669Z",
  "cog_version": "0.3.0",
  "openapi_schema": {...}
}
```

Every model describes its inputs and outputs with [OpenAPI Schema Objects](https://spec.openapis.org/oas/latest.html#schemaObject) in the `openapi_schema` property.

The `openapi_schema.components.schemas.Input` property for the [replicate/hello-world](https://replicate.com/replicate/hello-world) model looks like this:

Copy

```json
{
  "type": "object",
  "title": "Input",
  "required": [
    "text"
  ],
  "properties": {
    "text": {
      "x-order": 0,
      "type": "string",
      "title": "Text",
      "description": "Text to prefix with 'hello '"
    }
  }
}
```

The `openapi_schema.components.schemas.Output` property for the [replicate/hello-world](https://replicate.com/replicate/hello-world) model looks like this:

Copy

```json
{
  "type": "string",
  "title": "Output"
}
```

For more details, see the docs on [Cog’s supported input and output types](https://github.com/replicate/cog/blob/75b7802219e7cd4cee845e34c4c22139558615d4/docs/python.md#input-and-output-types)

### [](#models.versions.get-parameters)URL Parameters

*   [model\_owner](#models.versions.get-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.versions.get-parameters-modelname)stringRequired
    
    The name of the model.
    
*   [version\_id](#models.versions.get-parameters-versionid)stringRequired
    
    The ID of the version.
    

[](#models.versions.list)List model versions
--------------------------------------------

### [](#models.versions.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/models/{model_owner}/{model_name}/versions
```

### [](#models.versions.list-description)Description

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/models/replicate/hello-world/versions
```

The response will be a JSON array of model version objects, sorted with the most recent version first:

Copy

```json
{
  "next": null,
  "previous": null,
  "results": [
    {
      "id": "5c7d5dc6dd8bf75c1acaa8565735e7986bc5b66206b55cca93cb72c9bf15ccaa",
      "created_at": "2022-04-26T19:29:04.418669Z",
      "cog_version": "0.3.0",
      "openapi_schema": {...}
    }
  ]
}
```

### [](#models.versions.list-parameters)URL Parameters

*   [model\_owner](#models.versions.list-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.versions.list-parameters-modelname)stringRequired
    
    The name of the model.
    

[](#models.versions.delete)Delete a model version
-------------------------------------------------

### [](#models.versions.delete-endpoint)Endpoint

Copy

```plaintext
DELETE https://api.replicate.com/v1/models/{model_owner}/{model_name}/versions/{version_id}
```

### [](#models.versions.delete-description)Description

Delete a model version and all associated predictions, including all output files.

Model version deletion has some restrictions:

*   You can only delete versions from models you own.
*   You can only delete versions from private models.
*   You cannot delete a version if someone other than you has run predictions with it.
*   You cannot delete a version if it is being used as the base model for a fine tune/training.
*   You cannot delete a version if it has an associated deployment.
*   You cannot delete a version if another model version is overridden to use it.

Example cURL request:

Copy

```shell
curl -s -X DELETE \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/models/replicate/hello-world/versions/5c7d5dc6dd8bf75c1acaa8565735e7986bc5b66206b55cca93cb72c9bf15ccaa
```

The response will be an empty 202, indicating the deletion request has been accepted. It might take a few minutes to be processed.

### [](#models.versions.delete-parameters)URL Parameters

*   [model\_owner](#models.versions.delete-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#models.versions.delete-parameters-modelname)stringRequired
    
    The name of the model.
    
*   [version\_id](#models.versions.delete-parameters-versionid)stringRequired
    
    The ID of the version.
    

[](#collections.get)Get a collection of models
----------------------------------------------

### [](#collections.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/collections/{collection_slug}
```

### [](#collections.get-description)Description

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/collections/super-resolution
```

The response will be a collection object with a nested list of the models in that collection:

Copy

```json
{
  "name": "Super resolution",
  "slug": "super-resolution",
  "description": "Upscaling models that create high-quality images from low-quality images.",
  "models": [...]
}
```

### [](#collections.get-parameters)URL Parameters

*   [collection\_slug](#collections.get-parameters-collectionslug)stringRequired
    
    The slug of the collection, like `super-resolution` or `image-restoration`. See [replicate.com/collections](https://replicate.com/collections).
    

[](#collections.list)List collections of models
-----------------------------------------------

### [](#collections.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/collections
```

### [](#collections.list-description)Description

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/collections
```

The response will be a paginated JSON list of collection objects:

Copy

```json
{
  "next": "null",
  "previous": null,
  "results": [
    {
      "name": "Super resolution",
      "slug": "super-resolution",
      "description": "Upscaling models that create high-quality images from low-quality images."
    }
  ]
}
```

[](#deployments.create)Create a deployment
------------------------------------------

### [](#deployments.create-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/deployments
```

### [](#deployments.create-description)Description

Create a new deployment:

Example cURL request:

Copy

```shell
curl -s \
  -X POST \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
        "name": "my-app-image-generator",
        "model": "stability-ai/sdxl",
        "version": "da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf",
        "hardware": "gpu-t4",
        "min_instances": 0,
        "max_instances": 3
      }' \
  https://api.replicate.com/v1/deployments
```

The response will be a JSON object describing the deployment:

Copy

```json
{
  "owner": "acme",
  "name": "my-app-image-generator",
  "current_release": {
    "number": 1,
    "model": "stability-ai/sdxl",
    "version": "da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf",
    "created_at": "2024-02-15T16:32:57.018467Z",
    "created_by": {
      "type": "organization",
      "username": "acme",
      "name": "Acme Corp, Inc.",
      "avatar_url": "https://cdn.replicate.com/avatars/acme.png",
      "github_url": "https://github.com/acme"
    },
    "configuration": {
      "hardware": "gpu-t4",
      "min_instances": 1,
      "max_instances": 5
    }
  }
}
```

### [](#deployments.create-request-body)Request Body

*   [hardware](#deployments.create-request-body-hardware)stringRequired
    
    The SKU for the hardware used to run the model. Possible values can be retrieved from the `hardware.list` endpoint.
    
*   [max\_instances](#deployments.create-request-body-maxinstances)integerRequired
    
    The maximum number of instances for scaling.
    
*   [min\_instances](#deployments.create-request-body-mininstances)integerRequired
    
    The minimum number of instances for scaling.
    
*   [model](#deployments.create-request-body-model)stringRequired
    
    The full name of the model that you want to deploy e.g. stability-ai/sdxl.
    
*   [name](#deployments.create-request-body-name)stringRequired
    
    The name of the deployment.
    
*   [version](#deployments.create-request-body-version)stringRequired
    
    The 64-character string ID of the model version that you want to deploy.
    

[](#deployments.get)Get a deployment
------------------------------------

### [](#deployments.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/deployments/{deployment_owner}/{deployment_name}
```

### [](#deployments.get-description)Description

Get information about a deployment by name including the current release.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/deployments/replicate/my-app-image-generator
```

The response will be a JSON object describing the deployment:

Copy

```json
{
  "owner": "acme",
  "name": "my-app-image-generator",
  "current_release": {
    "number": 1,
    "model": "stability-ai/sdxl",
    "version": "da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf",
    "created_at": "2024-02-15T16:32:57.018467Z",
    "created_by": {
      "type": "organization",
      "username": "acme",
      "name": "Acme Corp, Inc.",
      "avatar_url": "https://cdn.replicate.com/avatars/acme.png",
      "github_url": "https://github.com/acme"
    },
    "configuration": {
      "hardware": "gpu-t4",
      "min_instances": 1,
      "max_instances": 5
    }
  }
}
```

### [](#deployments.get-parameters)URL Parameters

*   [deployment\_owner](#deployments.get-parameters-deploymentowner)stringRequired
    
    The name of the user or organization that owns the deployment.
    
*   [deployment\_name](#deployments.get-parameters-deploymentname)stringRequired
    
    The name of the deployment.
    

[](#deployments.list)List deployments
-------------------------------------

### [](#deployments.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/deployments
```

### [](#deployments.list-description)Description

Get a list of deployments associated with the current account, including the latest release configuration for each deployment.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/deployments
```

The response will be a paginated JSON array of deployment objects, sorted with the most recent deployment first:

Copy

```json
{
  "next": "http://api.replicate.com/v1/deployments?cursor=cD0yMDIzLTA2LTA2KzIzJTNBNDAlM0EwOC45NjMwMDAlMkIwMCUzQTAw",
  "previous": null,
  "results": [
    {
      "owner": "replicate",
      "name": "my-app-image-generator",
      "current_release": {
        "number": 1,
        "model": "stability-ai/sdxl",
        "version": "da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf",
        "created_at": "2024-02-15T16:32:57.018467Z",
        "created_by": {
          "type": "organization",
          "username": "acme",
          "name": "Acme Corp, Inc.",
          "avatar_url": "https://cdn.replicate.com/avatars/acme.png",
          "github_url": "https://github.com/acme"
        },
        "configuration": {
          "hardware": "gpu-t4",
          "min_instances": 1,
          "max_instances": 5
        }
      }
    }
  ]
}
```

[](#deployments.update)Update a deployment
------------------------------------------

### [](#deployments.update-endpoint)Endpoint

Copy

```plaintext
PATCH https://api.replicate.com/v1/deployments/{deployment_owner}/{deployment_name}
```

### [](#deployments.update-description)Description

Update properties of an existing deployment, including hardware, min/max instances, and the deployment’s underlying model [version](https://replicate.com/docs/how-does-replicate-work#versions).

Example cURL request:

Copy

```shell
curl -s \
  -X PATCH \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"min_instances": 3, "max_instances": 10}' \
  https://api.replicate.com/v1/deployments/acme/my-app-image-generator
```

The response will be a JSON object describing the deployment:

Copy

```json
{
  "owner": "acme",
  "name": "my-app-image-generator",
  "current_release": {
    "number": 2,
    "model": "stability-ai/sdxl",
    "version": "da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf",
    "created_at": "2024-02-15T16:32:57.018467Z",
    "created_by": {
      "type": "organization",
      "username": "acme",
      "name": "Acme Corp, Inc.",
      "avatar_url": "https://cdn.replicate.com/avatars/acme.png",
      "github_url": "https://github.com/acme"
    },
    "configuration": {
      "hardware": "gpu-t4",
      "min_instances": 3,
      "max_instances": 10
    }
  }
}
```

Updating any deployment properties will increment the `number` field of the `current_release`.

### [](#deployments.update-parameters)URL Parameters

*   [deployment\_owner](#deployments.update-parameters-deploymentowner)stringRequired
    
    The name of the user or organization that owns the deployment.
    
*   [deployment\_name](#deployments.update-parameters-deploymentname)stringRequired
    
    The name of the deployment.
    

### [](#deployments.update-request-body)Request Body

*   [hardware](#deployments.update-request-body-hardware)string
    
    The SKU for the hardware used to run the model. Possible values can be retrieved from the `hardware.list` endpoint.
    
*   [max\_instances](#deployments.update-request-body-maxinstances)integer
    
    The maximum number of instances for scaling.
    
*   [min\_instances](#deployments.update-request-body-mininstances)integer
    
    The minimum number of instances for scaling.
    
*   [version](#deployments.update-request-body-version)string
    
    The ID of the model version that you want to deploy
    

[](#deployments.delete)Delete a deployment
------------------------------------------

### [](#deployments.delete-endpoint)Endpoint

Copy

```plaintext
DELETE https://api.replicate.com/v1/deployments/{deployment_owner}/{deployment_name}
```

### [](#deployments.delete-description)Description

Delete a deployment

Deployment deletion has some restrictions:

*   You can only delete deployments that have been offline and unused for at least 15 minutes.

Example cURL request:

Copy

```shell
curl -s -X DELETE \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/deployments/acme/my-app-image-generator
```

The response will be an empty 204, indicating the deployment has been deleted.

### [](#deployments.delete-parameters)URL Parameters

*   [deployment\_owner](#deployments.delete-parameters-deploymentowner)stringRequired
    
    The name of the user or organization that owns the deployment.
    
*   [deployment\_name](#deployments.delete-parameters-deploymentname)stringRequired
    
    The name of the deployment.
    

[](#deployments.predictions.create)Create a prediction using a deployment
-------------------------------------------------------------------------

### [](#deployments.predictions.create-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/deployments/{deployment_owner}/{deployment_name}/predictions
```

### [](#deployments.predictions.create-description)Description

Create a prediction for the deployment and inputs you provide.

Example cURL request:

Copy

```shell
curl -s -X POST -H 'Prefer: wait' \
  -d '{"input": {"prompt": "A photo of a bear riding a bicycle over the moon"}}' \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H 'Content-Type: application/json' \
  https://api.replicate.com/v1/deployments/acme/my-app-image-generator/predictions
```

The request will wait up to 60 seconds for the model to run. If this time is exceeded the prediction will be returned in a `"starting"` state and need to be retrieved using the `predictions.get` endpoint.

For a complete overview of the `deployments.predictions.create` API check out our documentation on [creating a prediction](https://replicate.com/docs/topics/predictions/create-a-prediction) which covers a variety of use cases.

### [](#deployments.predictions.create-parameters)URL Parameters

*   [deployment\_owner](#deployments.predictions.create-parameters-deploymentowner)stringRequired
    
    The name of the user or organization that owns the deployment.
    
*   [deployment\_name](#deployments.predictions.create-parameters-deploymentname)stringRequired
    
    The name of the deployment.
    

### [](#deployments.predictions.create-headers)Headers

*   [Prefer](#deployments.predictions.create-headers-prefer)string
    
    Leave the request open and wait for the model to finish generating output. Set to `wait=n` where n is a number of seconds between 1 and 60.
    
    See https://replicate.com/docs/topics/predictions/create-a-prediction#sync-mode for more information.
    

### [](#deployments.predictions.create-request-body)Request Body

*   [input](#deployments.predictions.create-request-body-input)objectRequired
    
    The model's input as a JSON object. The input schema depends on what model you are running. To see the available inputs, click the "API" tab on the model you are running or [get the model version](#models.versions.get) and look at its `openapi_schema` property. For example, [stability-ai/sdxl](https://replicate.com/stability-ai/sdxl) takes `prompt` as an input.
    
    Files should be passed as HTTP URLs or data URLs.
    
    Use an HTTP URL when:
    
    *   you have a large file > 256kb
    *   you want to be able to use the file multiple times
    *   you want your prediction metadata to be associable with your input files
    
    Use a data URL when:
    
    *   you have a small file <= 256kb
    *   you don't want to upload and host the file somewhere
    *   you don't need to use the file again (Replicate will not store it)
    
*   [stream](#deployments.predictions.create-request-body-stream)boolean
    
    **This field is deprecated.**
    
    Request a URL to receive streaming output using [server-sent events (SSE)](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events).
    
    This field is no longer needed as the returned prediction will always have a `stream` entry in its `urls` property if the model supports streaming.
    
*   [webhook](#deployments.predictions.create-request-body-webhook)string
    
    An HTTPS URL for receiving a webhook when the prediction has new output. The webhook will be a POST request where the request body is the same as the response body of the [get prediction](#predictions.get) operation. If there are network problems, we will retry the webhook a few times, so make sure it can be safely called more than once. Replicate will not follow redirects when sending webhook requests to your service, so be sure to specify a URL that will resolve without redirecting.
    
*   [webhook\_events\_filter](#deployments.predictions.create-request-body-webhookeventsfilter)array
    
    By default, we will send requests to your webhook URL whenever there are new outputs or the prediction has finished. You can change which events trigger webhook requests by specifying `webhook_events_filter` in the prediction request:
    
    *   `start`: immediately on prediction start
    *   `output`: each time a prediction generates an output (note that predictions can generate multiple outputs)
    *   `logs`: each time log output is generated by a prediction
    *   `completed`: when the prediction reaches a terminal state (succeeded/canceled/failed)
    
    For example, if you only wanted requests to be sent at the start and end of the prediction, you would provide:
    
    {
      "input": {
        "text": "Alice"
      },
      "webhook": "https://example.com/my-webhook",
      "webhook_events_filter": ["start", "completed"]
    }
    
    Requests for event types `output` and `logs` will be sent at most once every 500ms. If you request `start` and `completed` webhooks, then they'll always be sent regardless of throttling.
    

[](#files.list)List files
-------------------------

### [](#files.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/files
```

### [](#files.list-description)Description

Get a paginated list of all files created by the user or organization associated with the provided API token.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Token $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/files
```

The response will be a paginated JSON array of file objects, sorted with the most recent file first.

[](#files.create)Create a file
------------------------------

### [](#files.create-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/files
```

### [](#files.create-description)Description

Create a file by uploading its content and optional metadata.

Example cURL request:

Copy

```shell
curl -X POST https://api.replicate.com/v1/files \
  -H "Authorization: Token $REPLICATE_API_TOKEN" \
  -H 'Content-Type: multipart/form-data' \
  -F 'content=@/path/to/archive.zip;type=application/zip;filename=example.zip' \
  -F 'metadata={"customer_reference_id": 123};type=application/json'
```

The request must include:

*   `content`: The file content (required)
*   `type`: The content / MIME type for the file (defaults to `application/octet-stream`)
*   `filename`: The filename (required, ≤ 255 bytes, valid UTF-8)
*   `metadata`: User-provided metadata associated with the file (defaults to `{}`, must be valid JSON)

[](#files.delete)Delete a file
------------------------------

### [](#files.delete-endpoint)Endpoint

Copy

```plaintext
DELETE https://api.replicate.com/v1/files/{file_id}
```

### [](#files.delete-description)Description

Delete a file. Once a file has been deleted, subsequent requests to the file resource return 404 Not found.

Example cURL request:

Copy

```shell
curl -X DELETE \
  -H "Authorization: Token $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/files/cneqzikepnug6xezperrr4z55o
```

### [](#files.delete-parameters)URL Parameters

*   [file\_id](#files.delete-parameters-fileid)stringRequired
    
    The ID of the file to delete
    

[](#files.get)Get a file
------------------------

### [](#files.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/files/{file_id}
```

### [](#files.get-description)Description

Get the details of a file.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Token $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/files/cneqzikepnug6xezperrr4z55o
```

### [](#files.get-parameters)URL Parameters

*   [file\_id](#files.get-parameters-fileid)stringRequired
    
    The ID of the file to get
    

[](#files.download)Download a file
----------------------------------

### [](#files.download-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/files/{file_id}/download
```

### [](#files.download-description)Description

Download a file by providing the file owner, access expiry, and a valid signature.

Example cURL request:

Copy

```shell
curl -X GET "https://api.replicate.com/v1/files/cneqzikepnug6xezperrr4z55o/download?expiry=1708515345&owner=mattt&signature=zuoghqlrcnw8YHywkpaXQlHsVhWen%2FDZ4aal76dLiOo%3D"
```

### [](#files.download-parameters)URL Parameters

*   [file\_id](#files.download-parameters-fileid)stringRequired
    
    The ID of the file to download
    

### [](#files.download-query-parameters)Query Parameters

*   [owner](#files.download-query-parameters-owner)stringRequired
    
    The username of the user or organization that uploaded the file
    
*   [expiry](#files.download-query-parameters-expiry)integerRequired
    
    A Unix timestamp with expiration date of this download URL
    
*   [signature](#files.download-query-parameters-signature)stringRequired
    
    A base64-encoded HMAC-SHA256 checksum of the string '{owner} {id} {expiry}' generated with the Files API signing secret
    

[](#trainings.create)Create a training
--------------------------------------

### [](#trainings.create-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/models/{model_owner}/{model_name}/versions/{version_id}/trainings
```

### [](#trainings.create-description)Description

Start a new training of the model version you specify.

Example request body:

Copy

```json
{
  "destination": "{new_owner}/{new_name}",
  "input": {
    "train_data": "https://example.com/my-input-images.zip",
  },
  "webhook": "https://example.com/my-webhook",
}
```

Example cURL request:

Copy

```shell
curl -s -X POST \
  -d '{"destination": "{new_owner}/{new_name}", "input": {"input_images": "https://example.com/my-input-images.zip"}}' \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  -H 'Content-Type: application/json' \
  https://api.replicate.com/v1/models/stability-ai/sdxl/versions/da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf/trainings
```

The response will be the training object:

Copy

```json
{
  "id": "zz4ibbonubfz7carwiefibzgga",
  "model": "stability-ai/sdxl",
  "version": "da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf",
  "input": {
    "input_images": "https://example.com/my-input-images.zip"
  },
  "logs": "",
  "error": null,
  "status": "starting",
  "created_at": "2023-09-08T16:32:56.990893084Z",
  "urls": {
    "web": "https://replicate.com/p/zz4ibbonubfz7carwiefibzgga",
     "get": "https://api.replicate.com/v1/predictions/zz4ibbonubfz7carwiefibzgga",
     "cancel": "https://api.replicate.com/v1/predictions/zz4ibbonubfz7carwiefibzgga/cancel"
  }
}
```

As models can take several minutes or more to train, the result will not be available immediately. To get the final result of the training you should either provide a `webhook` HTTPS URL for us to call when the results are ready, or poll the [get a training](#trainings.get) endpoint until it has finished.

When a training completes, it creates a new [version](https://replicate.com/docs/how-does-replicate-work#terminology) of the model at the specified destination.

To find some models to train on, check out the [trainable language models collection](https://replicate.com/collections/trainable-language-models).

### [](#trainings.create-parameters)URL Parameters

*   [model\_owner](#trainings.create-parameters-modelowner)stringRequired
    
    The name of the user or organization that owns the model.
    
*   [model\_name](#trainings.create-parameters-modelname)stringRequired
    
    The name of the model.
    
*   [version\_id](#trainings.create-parameters-versionid)stringRequired
    
    The ID of the version.
    

### [](#trainings.create-request-body)Request Body

*   [destination](#trainings.create-request-body-destination)stringRequired
    
    A string representing the desired model to push to in the format `{destination_model_owner}/{destination_model_name}`. This should be an existing model owned by the user or organization making the API request. If the destination is invalid, the server will return an appropriate 4XX response.
    
*   [input](#trainings.create-request-body-input)objectRequired
    
    An object containing inputs to the Cog model's `train()` function.
    
*   [webhook](#trainings.create-request-body-webhook)string
    
    An HTTPS URL for receiving a webhook when the training completes. The webhook will be a POST request where the request body is the same as the response body of the [get training](#trainings.get) operation. If there are network problems, we will retry the webhook a few times, so make sure it can be safely called more than once. Replicate will not follow redirects when sending webhook requests to your service, so be sure to specify a URL that will resolve without redirecting.
    
*   [webhook\_events\_filter](#trainings.create-request-body-webhookeventsfilter)array
    
    By default, we will send requests to your webhook URL whenever there are new outputs or the training has finished. You can change which events trigger webhook requests by specifying `webhook_events_filter` in the training request:
    
    *   `start`: immediately on training start
    *   `output`: each time a training generates an output (note that trainings can generate multiple outputs)
    *   `logs`: each time log output is generated by a training
    *   `completed`: when the training reaches a terminal state (succeeded/canceled/failed)
    
    For example, if you only wanted requests to be sent at the start and end of the training, you would provide:
    
    {
      "destination": "my-organization/my-model",
      "input": {
        "text": "Alice"
      },
      "webhook": "https://example.com/my-webhook",
      "webhook_events_filter": ["start", "completed"]
    }
    
    Requests for event types `output` and `logs` will be sent at most once every 500ms. If you request `start` and `completed` webhooks, then they'll always be sent regardless of throttling.
    

[](#trainings.get)Get a training
--------------------------------

### [](#trainings.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/trainings/{training_id}
```

### [](#trainings.get-description)Description

Get the current state of a training.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/trainings/zz4ibbonubfz7carwiefibzgga
```

The response will be the training object:

Copy

```json
{
  "completed_at": "2023-09-08T16:41:19.826523Z",
  "created_at": "2023-09-08T16:32:57.018467Z",
  "error": null,
  "id": "zz4ibbonubfz7carwiefibzgga",
  "input": {
    "input_images": "https://example.com/my-input-images.zip"
  },
  "logs": "...",
  "metrics": {
    "predict_time": 502.713876
  },
  "output": {
    "version": "...",
    "weights": "..."
  },
  "started_at": "2023-09-08T16:32:57.112647Z",
  "status": "succeeded",
  "urls": {
    "web": "https://replicate.com/p/zz4ibbonubfz7carwiefibzgga",
    "get": "https://api.replicate.com/v1/trainings/zz4ibbonubfz7carwiefibzgga",
    "cancel": "https://api.replicate.com/v1/trainings/zz4ibbonubfz7carwiefibzgga/cancel"
  },
  "model": "stability-ai/sdxl",
  "version": "da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf",
}
```

`status` will be one of:

*   `starting`: the training is starting up. If this status lasts longer than a few seconds, then it’s typically because a new worker is being started to run the training.
*   `processing`: the `train()` method of the model is currently running.
*   `succeeded`: the training completed successfully.
*   `failed`: the training encountered an error during processing.
*   `canceled`: the training was canceled by its creator.

In the case of success, `output` will be an object containing the output of the model. Any files will be represented as HTTPS URLs. You’ll need to pass the `Authorization` header to request them.

In the case of failure, `error` will contain the error encountered during the training.

Terminated trainings (with a status of `succeeded`, `failed`, or `canceled`) will include a `metrics` object with a `predict_time` property showing the amount of CPU or GPU time, in seconds, that the training used while running. It won’t include time waiting for the training to start. The `metrics` object will also include a `total_time` property showing the total time, in seconds, that the training took to complete.

### [](#trainings.get-parameters)URL Parameters

*   [training\_id](#trainings.get-parameters-trainingid)stringRequired
    
    The ID of the training to get.
    

[](#trainings.list)List trainings
---------------------------------

### [](#trainings.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/trainings
```

### [](#trainings.list-description)Description

Get a paginated list of all trainings created by the user or organization associated with the provided API token.

This will include trainings created from the API and the website. It will return 100 records per page.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/trainings
```

The response will be a paginated JSON array of training objects, sorted with the most recent training first:

Copy

```json
{
  "next": null,
  "previous": null,
  "results": [
    {
      "completed_at": "2023-09-08T16:41:19.826523Z",
      "created_at": "2023-09-08T16:32:57.018467Z",
      "error": null,
      "id": "zz4ibbonubfz7carwiefibzgga",
      "input": {
        "input_images": "https://example.com/my-input-images.zip"
      },
      "metrics": {
        "predict_time": 502.713876
      },
      "output": {
        "version": "...",
        "weights": "..."
      },
      "started_at": "2023-09-08T16:32:57.112647Z",
      "source": "api",
      "status": "succeeded",
      "urls": {
        "web": "https://replicate.com/p/zz4ibbonubfz7carwiefibzgga",
        "get": "https://api.replicate.com/v1/trainings/zz4ibbonubfz7carwiefibzgga",
        "cancel": "https://api.replicate.com/v1/trainings/zz4ibbonubfz7carwiefibzgga/cancel"
      },
      "model": "stability-ai/sdxl",
      "version": "da77bc59ee60423279fd632efb4795ab731d9e3ca9705ef3341091fb989b7eaf",
    }
  ]
}
```

`id` will be the unique ID of the training.

`source` will indicate how the training was created. Possible values are `web` or `api`.

`status` will be the status of the training. Refer to [get a single training](#trainings.get) for possible values.

`urls` will be a convenience object that can be used to construct new API requests for the given training.

`version` will be the unique ID of model version used to create the training.

[](#trainings.cancel)Cancel a training
--------------------------------------

### [](#trainings.cancel-endpoint)Endpoint

Copy

```plaintext
POST https://api.replicate.com/v1/trainings/{training_id}/cancel
```

### [](#trainings.cancel-parameters)URL Parameters

*   [training\_id](#trainings.cancel-parameters-trainingid)stringRequired
    
    The ID of the training you want to cancel.
    

[](#hardware.list)List available hardware for models
----------------------------------------------------

### [](#hardware.list-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/hardware
```

### [](#hardware.list-description)Description

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/hardware
```

The response will be a JSON array of hardware objects:

Copy

```json
[
    {"name": "CPU", "sku": "cpu"},
    {"name": "Nvidia T4 GPU", "sku": "gpu-t4"},
    {"name": "Nvidia A40 GPU", "sku": "gpu-a40-small"},
    {"name": "Nvidia A40 (Large) GPU", "sku": "gpu-a40-large"},
]
```

[](#account.get)Get the authenticated account
---------------------------------------------

### [](#account.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/account
```

### [](#account.get-description)Description

Returns information about the user or organization associated with the provided API token.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/account
```

The response will be a JSON object describing the account:

Copy

```json
{
  "type": "organization",
  "username": "acme",
  "name": "Acme Corp, Inc.",
  "github_url": "https://github.com/acme",
}
```

[](#webhooks.default.secret.get)Get the signing secret for the default webhook
------------------------------------------------------------------------------

### [](#webhooks.default.secret.get-endpoint)Endpoint

Copy

```plaintext
GET https://api.replicate.com/v1/webhooks/default/secret
```

### [](#webhooks.default.secret.get-description)Description

Get the signing secret for the default webhook endpoint. This is used to verify that webhook requests are coming from Replicate.

Example cURL request:

Copy

```shell
curl -s \
  -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
  https://api.replicate.com/v1/webhooks/default/secret
```

The response will be a JSON object with a `key` property:

Copy

```json
{
  "key": "..."
}
```

[](#rate-limits)Rate limits
---------------------------

We limit the number of API requests that can be made to Replicate:

*   You can call [create prediction](#create-a-prediction) at 600 requests per minute.
*   All other endpoints you can call at 3000 requests per minute.

If you hit a limit, you will receive a response with status `429` with a body like:

```json
{"detail":"Request was throttled. Expected available in 1 second."}
```

If you want higher limits, [contact us](https://replicate.com/support).

[](#openapi-schema)OpenAPI schema
---------------------------------

Replicate’s public HTTP API documentation is available as a machine-readable OpenAPI schema in JSON format.

See [OpenAPI schema](/docs/reference/openapi) to learn more and download the schema.