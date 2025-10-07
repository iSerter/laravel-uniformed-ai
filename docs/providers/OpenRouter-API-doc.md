---
title: Quickstart
subtitle: Get started with OpenRouter
slug: quickstart
headline: OpenRouter Quickstart Guide | Developer Documentation
canonical-url: 'https://openrouter.ai/docs/quickstart'
'og:site_name': OpenRouter Documentation
'og:title': OpenRouter Quickstart Guide
'og:description': >-
  Get started with OpenRouter's unified API for hundreds of AI models. Learn how
  to integrate using OpenAI SDK, direct API calls, or third-party frameworks.
'og:image':
  type: url
  value: >-
    https://openrouter.ai/dynamic-og?pathname=quickstart&title=Quick%20Start&description=Start%20using%20OpenRouter%20API%20in%20minutes%20with%20any%20SDK
'og:image:width': 1200
'og:image:height': 630
'twitter:card': summary_large_image
'twitter:site': '@OpenRouterAI'
noindex: false
nofollow: false
---

OpenRouter provides a unified API that gives you access to hundreds of AI models through a single endpoint, while automatically handling fallbacks and selecting the most cost-effective options. Get started with just a few lines of code using your preferred SDK or framework.

<Tip>
  Looking for information about free models and rate limits? Please see the [FAQ](/docs/faq#how-are-rate-limits-calculated)
</Tip>

In the examples below, the OpenRouter-specific headers are optional. Setting them allows your app to appear on the OpenRouter leaderboards. For detailed information about app attribution, see our [App Attribution guide](/docs/features/app-attribution).

## Using the OpenAI SDK

<CodeGroup>

```python title="Python"
from openai import OpenAI

client = OpenAI(
  base_url="https://openrouter.ai/api/v1",
  api_key="<OPENROUTER_API_KEY>",
)

completion = client.chat.completions.create(
  extra_headers={
    "HTTP-Referer": "<YOUR_SITE_URL>", # Optional. Site URL for rankings on openrouter.ai.
    "X-Title": "<YOUR_SITE_NAME>", # Optional. Site title for rankings on openrouter.ai.
  },
  model="openai/gpt-4o",
  messages=[
    {
      "role": "user",
      "content": "What is the meaning of life?"
    }
  ]
)

print(completion.choices[0].message.content)
```

```typescript title="TypeScript"
import OpenAI from 'openai';

const openai = new OpenAI({
  baseURL: 'https://openrouter.ai/api/v1',
  apiKey: '<OPENROUTER_API_KEY>',
  defaultHeaders: {
    'HTTP-Referer': '<YOUR_SITE_URL>', // Optional. Site URL for rankings on openrouter.ai.
    'X-Title': '<YOUR_SITE_NAME>', // Optional. Site title for rankings on openrouter.ai.
  },
});

async function main() {
  const completion = await openai.chat.completions.create({
    model: 'openai/gpt-4o',
    messages: [
      {
        role: 'user',
        content: 'What is the meaning of life?',
      },
    ],
  });

  console.log(completion.choices[0].message);
}

main();
```

</CodeGroup>

## Using the OpenRouter API directly

<Tip>
  You can use the interactive [Request Builder](/request-builder) to generate OpenRouter API requests in the language of your choice.
</Tip>

<CodeGroup>

```python title="Python"
import requests
import json

response = requests.post(
  url="https://openrouter.ai/api/v1/chat/completions",
  headers={
    "Authorization": "Bearer <OPENROUTER_API_KEY>",
    "HTTP-Referer": "<YOUR_SITE_URL>", # Optional. Site URL for rankings on openrouter.ai.
    "X-Title": "<YOUR_SITE_NAME>", # Optional. Site title for rankings on openrouter.ai.
  },
  data=json.dumps({
    "model": "openai/gpt-4o", # Optional
    "messages": [
      {
        "role": "user",
        "content": "What is the meaning of life?"
      }
    ]
  })
)
```

```typescript title="TypeScript"
fetch('https://openrouter.ai/api/v1/chat/completions', {
  method: 'POST',
  headers: {
    Authorization: 'Bearer <OPENROUTER_API_KEY>',
    'HTTP-Referer': '<YOUR_SITE_URL>', // Optional. Site URL for rankings on openrouter.ai.
    'X-Title': '<YOUR_SITE_NAME>', // Optional. Site title for rankings on openrouter.ai.
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    model: 'openai/gpt-4o',
    messages: [
      {
        role: 'user',
        content: 'What is the meaning of life?',
      },
    ],
  }),
});
```

```shell title="Shell"
curl https://openrouter.ai/api/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $OPENROUTER_API_KEY" \
  -d '{
  "model": "openai/gpt-4o",
  "messages": [
    {
      "role": "user",
      "content": "What is the meaning of life?"
    }
  ]
}'
```

</CodeGroup>


---
title: Streaming
headline: API Streaming | Real-time Model Responses in OpenRouter
canonical-url: 'https://openrouter.ai/docs/api-reference/streaming'
'og:site_name': OpenRouter Documentation
'og:title': API Streaming - Real-time Model Response Integration
'og:description': >-
  Learn how to implement streaming responses with OpenRouter's API. Complete
  guide to Server-Sent Events (SSE) and real-time model outputs.
'og:image':
  type: url
  value: >-
    https://openrouter.ai/dynamic-og?title=API%20Streaming&description=Real-time%20model%20response%20streaming
'og:image:width': 1200
'og:image:height': 630
'twitter:card': summary_large_image
'twitter:site': '@OpenRouterAI'
noindex: false
nofollow: false
---

import { API_KEY_REF, Model } from '../../../imports/constants';

The OpenRouter API allows streaming responses from _any model_. This is useful for building chat interfaces or other applications where the UI should update as the model generates the response.

To enable streaming, you can set the `stream` parameter to `true` in your request. The model will then stream the response to the client in chunks, rather than returning the entire response at once.

Here is an example of how to stream a response, and process it:

<Template data={{
  API_KEY_REF,
  MODEL: Model.GPT_4_Omni
}}>

<CodeGroup>

```python Python
import requests
import json

question = "How would you build the tallest building ever?"

url = "https://openrouter.ai/api/v1/chat/completions"
headers = {
  "Authorization": f"Bearer {{API_KEY_REF}}",
  "Content-Type": "application/json"
}

payload = {
  "model": "{{MODEL}}",
  "messages": [{"role": "user", "content": question}],
  "stream": True
}

buffer = ""
with requests.post(url, headers=headers, json=payload, stream=True) as r:
  for chunk in r.iter_content(chunk_size=1024, decode_unicode=True):
    buffer += chunk
    while True:
      try:
        # Find the next complete SSE line
        line_end = buffer.find('\n')
        if line_end == -1:
          break

        line = buffer[:line_end].strip()
        buffer = buffer[line_end + 1:]

        if line.startswith('data: '):
          data = line[6:]
          if data == '[DONE]':
            break

          try:
            data_obj = json.loads(data)
            content = data_obj["choices"][0]["delta"].get("content")
            if content:
              print(content, end="", flush=True)
          except json.JSONDecodeError:
            pass
      except Exception:
        break
```

```typescript TypeScript
const question = 'How would you build the tallest building ever?';
const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${API_KEY_REF}`,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    model: '{{MODEL}}',
    messages: [{ role: 'user', content: question }],
    stream: true,
  }),
});

const reader = response.body?.getReader();
if (!reader) {
  throw new Error('Response body is not readable');
}

const decoder = new TextDecoder();
let buffer = '';

try {
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    // Append new chunk to buffer
    buffer += decoder.decode(value, { stream: true });

    // Process complete lines from buffer
    while (true) {
      const lineEnd = buffer.indexOf('\n');
      if (lineEnd === -1) break;

      const line = buffer.slice(0, lineEnd).trim();
      buffer = buffer.slice(lineEnd + 1);

      if (line.startsWith('data: ')) {
        const data = line.slice(6);
        if (data === '[DONE]') break;

        try {
          const parsed = JSON.parse(data);
          const content = parsed.choices[0].delta.content;
          if (content) {
            console.log(content);
          }
        } catch (e) {
          // Ignore invalid JSON
        }
      }
    }
  }
} finally {
  reader.cancel();
}
```

</CodeGroup>
</Template>

### Additional Information

For SSE (Server-Sent Events) streams, OpenRouter occasionally sends comments to prevent connection timeouts. These comments look like:

```text
: OPENROUTER PROCESSING
```

Comment payload can be safely ignored per the [SSE specs](https://html.spec.whatwg.org/multipage/server-sent-events.html#event-stream-interpretation). However, you can leverage it to improve UX as needed, e.g. by showing a dynamic loading indicator.

Some SSE client implementations might not parse the payload according to spec, which leads to an uncaught error when you `JSON.stringify` the non-JSON payloads. We recommend the following clients:

- [eventsource-parser](https://github.com/rexxars/eventsource-parser)
- [OpenAI SDK](https://www.npmjs.com/package/openai)
- [Vercel AI SDK](https://www.npmjs.com/package/ai)

### Stream Cancellation

Streaming requests can be cancelled by aborting the connection. For supported providers, this immediately stops model processing and billing.

<Accordion title="Provider Support">

**Supported**

- OpenAI, Azure, Anthropic
- Fireworks, Mancer, Recursal
- AnyScale, Lepton, OctoAI
- Novita, DeepInfra, Together
- Cohere, Hyperbolic, Infermatic
- Avian, XAI, Cloudflare
- SFCompute, Nineteen, Liquid
- Friendli, Chutes, DeepSeek

**Not Currently Supported**

- AWS Bedrock, Groq, Modal
- Google, Google AI Studio, Minimax
- HuggingFace, Replicate, Perplexity
- Mistral, AI21, Featherless
- Lynn, Lambda, Reflection
- SambaNova, Inflection, ZeroOneAI
- AionLabs, Alibaba, Nebius
- Kluster, Targon, InferenceNet

</Accordion>

To implement stream cancellation:

<Template data={{
  API_KEY_REF,
  MODEL: Model.GPT_4_Omni
}}>

<CodeGroup>

```python Python
import requests
from threading import Event, Thread

def stream_with_cancellation(prompt: str, cancel_event: Event):
    with requests.Session() as session:
        response = session.post(
            "https://openrouter.ai/api/v1/chat/completions",
            headers={"Authorization": f"Bearer {{API_KEY_REF}}"},
            json={"model": "{{MODEL}}", "messages": [{"role": "user", "content": prompt}], "stream": True},
            stream=True
        )

        try:
            for line in response.iter_lines():
                if cancel_event.is_set():
                    response.close()
                    return
                if line:
                    print(line.decode(), end="", flush=True)
        finally:
            response.close()

# Example usage:
cancel_event = Event()
stream_thread = Thread(target=lambda: stream_with_cancellation("Write a story", cancel_event))
stream_thread.start()

# To cancel the stream:
cancel_event.set()
```

```typescript TypeScript
const controller = new AbortController();

try {
  const response = await fetch(
    'https://openrouter.ai/api/v1/chat/completions',
    {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${{{API_KEY_REF}}}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        model: '{{MODEL}}',
        messages: [{ role: 'user', content: 'Write a story' }],
        stream: true,
      }),
      signal: controller.signal,
    },
  );

  // Process the stream...
} catch (error) {
  if (error.name === 'AbortError') {
    console.log('Stream cancelled');
  } else {
    throw error;
  }
}

// To cancel the stream:
controller.abort();
```

</CodeGroup>
</Template>

<Warning>
  Cancellation only works for streaming requests with supported providers. For
  non-streaming requests or unsupported providers, the model will continue
  processing and you will be billed for the complete response.
</Warning>

### Handling Errors During Streaming

OpenRouter handles errors differently depending on when they occur during the streaming process:

#### Errors Before Any Tokens Are Sent

If an error occurs before any tokens have been streamed to the client, OpenRouter returns a standard JSON error response with the appropriate HTTP status code. This follows the standard error format:

```json
{
  "error": {
    "code": 400,
    "message": "Invalid model specified"
  }
}
```

Common HTTP status codes include:
- **400**: Bad Request (invalid parameters)
- **401**: Unauthorized (invalid API key)
- **402**: Payment Required (insufficient credits)
- **429**: Too Many Requests (rate limited)
- **502**: Bad Gateway (provider error)
- **503**: Service Unavailable (no available providers)

#### Errors After Tokens Have Been Sent (Mid-Stream)

If an error occurs after some tokens have already been streamed to the client, OpenRouter cannot change the HTTP status code (which is already 200 OK). Instead, the error is sent as a Server-Sent Event (SSE) with a unified structure:

```text
data: {"id":"cmpl-abc123","object":"chat.completion.chunk","created":1234567890,"model":"gpt-3.5-turbo","provider":"openai","error":{"code":"server_error","message":"Provider disconnected unexpectedly"},"choices":[{"index":0,"delta":{"content":""},"finish_reason":"error"}]}
```

Key characteristics of mid-stream errors:
- The error appears at the **top level** alongside standard response fields (id, object, created, etc.)
- A `choices` array is included with `finish_reason: "error"` to properly terminate the stream
- The HTTP status remains 200 OK since headers were already sent
- The stream is terminated after this unified error event

#### Code Examples

Here's how to properly handle both types of errors in your streaming implementation:

<Template data={{
  API_KEY_REF,
  MODEL: Model.GPT_4_Omni
}}>

<CodeGroup>

```python Python
import requests
import json

async def stream_with_error_handling(prompt):
    response = requests.post(
        'https://openrouter.ai/api/v1/chat/completions',
        headers={'Authorization': f'Bearer {{API_KEY_REF}}'},
        json={
            'model': '{{MODEL}}',
            'messages': [{'role': 'user', 'content': prompt}],
            'stream': True
        },
        stream=True
    )
    
    # Check initial HTTP status for pre-stream errors
    if response.status_code != 200:
        error_data = response.json()
        print(f"Error: {error_data['error']['message']}")
        return
    
    # Process stream and handle mid-stream errors
    for line in response.iter_lines():
        if line:
            line_text = line.decode('utf-8')
            if line_text.startswith('data: '):
                data = line_text[6:]
                if data == '[DONE]':
                    break
                
                try:
                    parsed = json.loads(data)
                    
                    # Check for mid-stream error
                    if 'error' in parsed:
                        print(f"Stream error: {parsed['error']['message']}")
                        # Check finish_reason if needed
                        if parsed.get('choices', [{}])[0].get('finish_reason') == 'error':
                            print("Stream terminated due to error")
                        break
                    
                    # Process normal content
                    content = parsed['choices'][0]['delta'].get('content')
                    if content:
                        print(content, end='', flush=True)
                        
                except json.JSONDecodeError:
                    pass
```

```typescript TypeScript
async function streamWithErrorHandling(prompt: string) {
  const response = await fetch(
    'https://openrouter.ai/api/v1/chat/completions',
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${{{API_KEY_REF}}}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        model: '{{MODEL}}',
        messages: [{ role: 'user', content: prompt }],
        stream: true,
      }),
    }
  );

  // Check initial HTTP status for pre-stream errors
  if (!response.ok) {
    const error = await response.json();
    console.error(`Error: ${error.error.message}`);
    return;
  }

  const reader = response.body?.getReader();
  if (!reader) throw new Error('No response body');

  const decoder = new TextDecoder();
  let buffer = '';

  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });

      while (true) {
        const lineEnd = buffer.indexOf('\n');
        if (lineEnd === -1) break;

        const line = buffer.slice(0, lineEnd).trim();
        buffer = buffer.slice(lineEnd + 1);

        if (line.startsWith('data: ')) {
          const data = line.slice(6);
          if (data === '[DONE]') return;

          try {
            const parsed = JSON.parse(data);
            
            // Check for mid-stream error
            if (parsed.error) {
              console.error(`Stream error: ${parsed.error.message}`);
              // Check finish_reason if needed
              if (parsed.choices?.[0]?.finish_reason === 'error') {
                console.log('Stream terminated due to error');
              }
              return;
            }

            // Process normal content
            const content = parsed.choices[0].delta.content;
            if (content) {
              console.log(content);
            }
          } catch (e) {
            // Ignore parsing errors
          }
        }
      }
    }
  } finally {
    reader.cancel();
  }
}
```

</CodeGroup>
</Template>

#### API-Specific Behavior

Different API endpoints may handle streaming errors slightly differently:

- **OpenAI Chat Completions API**: Returns `ErrorResponse` directly if no chunks were processed, or includes error information in the response if some chunks were processed
- **OpenAI Responses API**: May transform certain error codes (like `context_length_exceeded`) into a successful response with `finish_reason: "length"` instead of treating them as errors


--- 

# Completion

POST https://openrouter.ai/api/v1/completions
Content-Type: application/json

Send a completion request to a selected model (text-only format)

Reference: https://openrouter.ai/docs/api-reference/completion

## OpenAPI Specification

```yaml
openapi: 3.1.1
info:
  title: Completion
  version: endpoint_.completion
paths:
  /completions:
    post:
      operationId: completion
      summary: Completion
      description: Send a completion request to a selected model (text-only format)
      tags:
        - []
      parameters:
        - name: Authorization
          in: header
          description: >-
            Bearer authentication of the form `Bearer <token>`, where token is
            your auth token.
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Successful completion
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/completion_Response_200'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                model:
                  type: string
                prompt:
                  type: string
                models:
                  type: array
                  items:
                    type: string
                provider:
                  $ref: >-
                    #/components/schemas/CompletionsPostRequestBodyContentApplicationJsonSchemaProvider
                reasoning:
                  $ref: >-
                    #/components/schemas/CompletionsPostRequestBodyContentApplicationJsonSchemaReasoning
                usage:
                  $ref: >-
                    #/components/schemas/CompletionsPostRequestBodyContentApplicationJsonSchemaUsage
                transforms:
                  type: array
                  items:
                    type: string
                stream:
                  type: boolean
                max_tokens:
                  type: integer
                temperature:
                  type: number
                  format: double
                seed:
                  type: integer
                top_p:
                  type: number
                  format: double
                top_k:
                  type: integer
                frequency_penalty:
                  type: number
                  format: double
                presence_penalty:
                  type: number
                  format: double
                repetition_penalty:
                  type: number
                  format: double
                logit_bias:
                  type: object
                  additionalProperties:
                    type: number
                    format: double
                top_logprobs:
                  type: integer
                min_p:
                  type: number
                  format: double
                top_a:
                  type: number
                  format: double
                user:
                  type: string
              required:
                - model
                - prompt
components:
  schemas:
    CompletionsPostRequestBodyContentApplicationJsonSchemaProvider:
      type: object
      properties:
        sort:
          type: string
    CompletionsPostRequestBodyContentApplicationJsonSchemaReasoningEffort:
      type: string
      enum:
        - value: high
        - value: medium
        - value: low
    CompletionsPostRequestBodyContentApplicationJsonSchemaReasoning:
      type: object
      properties:
        effort:
          $ref: >-
            #/components/schemas/CompletionsPostRequestBodyContentApplicationJsonSchemaReasoningEffort
        max_tokens:
          type: integer
        exclude:
          type: boolean
    CompletionsPostRequestBodyContentApplicationJsonSchemaUsage:
      type: object
      properties:
        include:
          type: boolean
    CompletionsPostResponsesContentApplicationJsonSchemaChoicesItems:
      type: object
      properties:
        text:
          type: string
        index:
          type: integer
        finish_reason:
          type: string
    completion_Response_200:
      type: object
      properties:
        id:
          type: string
        choices:
          type: array
          items:
            $ref: >-
              #/components/schemas/CompletionsPostResponsesContentApplicationJsonSchemaChoicesItems

```

## SDK Code Examples

```python
import requests

url = "https://openrouter.ai/api/v1/completions"

payload = {
    "model": "string",
    "prompt": "string"
}
headers = {
    "Authorization": "Bearer <token>",
    "Content-Type": "application/json"
}

response = requests.post(url, json=payload, headers=headers)

print(response.json())
```

```javascript
const url = 'https://openrouter.ai/api/v1/completions';
const options = {
  method: 'POST',
  headers: {Authorization: 'Bearer <token>', 'Content-Type': 'application/json'},
  body: '{"model":"string","prompt":"string"}'
};

try {
  const response = await fetch(url, options);
  const data = await response.json();
  console.log(data);
} catch (error) {
  console.error(error);
}
```

```go
package main

import (
	"fmt"
	"strings"
	"net/http"
	"io"
)

func main() {

	url := "https://openrouter.ai/api/v1/completions"

	payload := strings.NewReader("{\n  \"model\": \"string\",\n  \"prompt\": \"string\"\n}")

	req, _ := http.NewRequest("POST", url, payload)

	req.Header.Add("Authorization", "Bearer <token>")
	req.Header.Add("Content-Type", "application/json")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := io.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

```ruby
require 'uri'
require 'net/http'

url = URI("https://openrouter.ai/api/v1/completions")

http = Net::HTTP.new(url.host, url.port)
http.use_ssl = true

request = Net::HTTP::Post.new(url)
request["Authorization"] = 'Bearer <token>'
request["Content-Type"] = 'application/json'
request.body = "{\n  \"model\": \"string\",\n  \"prompt\": \"string\"\n}"

response = http.request(request)
puts response.read_body
```

```java
HttpResponse<String> response = Unirest.post("https://openrouter.ai/api/v1/completions")
  .header("Authorization", "Bearer <token>")
  .header("Content-Type", "application/json")
  .body("{\n  \"model\": \"string\",\n  \"prompt\": \"string\"\n}")
  .asString();
```

```php
<?php

$client = new \GuzzleHttp\Client();

$response = $client->request('POST', 'https://openrouter.ai/api/v1/completions', [
  'body' => '{
  "model": "string",
  "prompt": "string"
}',
  'headers' => [
    'Authorization' => 'Bearer <token>',
    'Content-Type' => 'application/json',
  ],
]);

echo $response->getBody();
```

```csharp
var client = new RestClient("https://openrouter.ai/api/v1/completions");
var request = new RestRequest(Method.POST);
request.AddHeader("Authorization", "Bearer <token>");
request.AddHeader("Content-Type", "application/json");
request.AddParameter("application/json", "{\n  \"model\": \"string\",\n  \"prompt\": \"string\"\n}", ParameterType.RequestBody);
IRestResponse response = client.Execute(request);
```

```swift
import Foundation

let headers = [
  "Authorization": "Bearer <token>",
  "Content-Type": "application/json"
]
let parameters = [
  "model": "string",
  "prompt": "string"
] as [String : Any]

let postData = JSONSerialization.data(withJSONObject: parameters, options: [])

let request = NSMutableURLRequest(url: NSURL(string: "https://openrouter.ai/api/v1/completions")! as URL,
                                        cachePolicy: .useProtocolCachePolicy,
                                    timeoutInterval: 10.0)
request.httpMethod = "POST"
request.allHTTPHeaderFields = headers
request.httpBody = postData as Data

let session = URLSession.shared
let dataTask = session.dataTask(with: request as URLRequest, completionHandler: { (data, response, error) -> Void in
  if (error != nil) {
    print(error as Any)
  } else {
    let httpResponse = response as? HTTPURLResponse
    print(httpResponse)
  }
})

dataTask.resume()
```

# Chat completion

POST https://openrouter.ai/api/v1/chat/completions
Content-Type: application/json

Send a chat completion request to a selected model. The request must contain a "messages" array. All advanced options from the base request are also supported.

Reference: https://openrouter.ai/docs/api-reference/chat-completion

## OpenAPI Specification

```yaml
openapi: 3.1.1
info:
  title: Chat completion
  version: endpoint_.chatCompletion
paths:
  /chat/completions:
    post:
      operationId: chat-completion
      summary: Chat completion
      description: >-
        Send a chat completion request to a selected model. The request must
        contain a "messages" array. All advanced options from the base request
        are also supported.
      tags:
        - []
      parameters:
        - name: Authorization
          in: header
          description: >-
            Bearer authentication of the form `Bearer <token>`, where token is
            your auth token.
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Successful completion
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/chatCompletion_Response_200'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                model:
                  type: string
                messages:
                  type: array
                  items:
                    $ref: >-
                      #/components/schemas/ChatCompletionsPostRequestBodyContentApplicationJsonSchemaMessagesItems
                models:
                  type: array
                  items:
                    type: string
                provider:
                  $ref: >-
                    #/components/schemas/ChatCompletionsPostRequestBodyContentApplicationJsonSchemaProvider
                reasoning:
                  $ref: >-
                    #/components/schemas/ChatCompletionsPostRequestBodyContentApplicationJsonSchemaReasoning
                usage:
                  $ref: >-
                    #/components/schemas/ChatCompletionsPostRequestBodyContentApplicationJsonSchemaUsage
                transforms:
                  type: array
                  items:
                    type: string
                stream:
                  type: boolean
                max_tokens:
                  type: integer
                temperature:
                  type: number
                  format: double
                seed:
                  type: integer
                top_p:
                  type: number
                  format: double
                top_k:
                  type: integer
                frequency_penalty:
                  type: number
                  format: double
                presence_penalty:
                  type: number
                  format: double
                repetition_penalty:
                  type: number
                  format: double
                logit_bias:
                  type: object
                  additionalProperties:
                    type: number
                    format: double
                top_logprobs:
                  type: integer
                min_p:
                  type: number
                  format: double
                top_a:
                  type: number
                  format: double
                user:
                  type: string
              required:
                - model
                - messages
components:
  schemas:
    ChatCompletionsPostRequestBodyContentApplicationJsonSchemaMessagesItemsRole:
      type: string
      enum:
        - value: system
        - value: developer
        - value: user
        - value: assistant
        - value: tool
    ChatCompletionsPostRequestBodyContentApplicationJsonSchemaMessagesItems:
      type: object
      properties:
        role:
          $ref: >-
            #/components/schemas/ChatCompletionsPostRequestBodyContentApplicationJsonSchemaMessagesItemsRole
        content:
          type: string
      required:
        - role
        - content
    ChatCompletionsPostRequestBodyContentApplicationJsonSchemaProvider:
      type: object
      properties:
        sort:
          type: string
    ChatCompletionsPostRequestBodyContentApplicationJsonSchemaReasoningEffort:
      type: string
      enum:
        - value: high
        - value: medium
        - value: low
    ChatCompletionsPostRequestBodyContentApplicationJsonSchemaReasoning:
      type: object
      properties:
        effort:
          $ref: >-
            #/components/schemas/ChatCompletionsPostRequestBodyContentApplicationJsonSchemaReasoningEffort
        max_tokens:
          type: integer
        exclude:
          type: boolean
    ChatCompletionsPostRequestBodyContentApplicationJsonSchemaUsage:
      type: object
      properties:
        include:
          type: boolean
    ChatCompletionsPostResponsesContentApplicationJsonSchemaChoicesItemsMessage:
      type: object
      properties:
        role:
          type: string
        content:
          type: string
    ChatCompletionsPostResponsesContentApplicationJsonSchemaChoicesItems:
      type: object
      properties:
        message:
          $ref: >-
            #/components/schemas/ChatCompletionsPostResponsesContentApplicationJsonSchemaChoicesItemsMessage
    chatCompletion_Response_200:
      type: object
      properties:
        id:
          type: string
        choices:
          type: array
          items:
            $ref: >-
              #/components/schemas/ChatCompletionsPostResponsesContentApplicationJsonSchemaChoicesItems

```

## SDK Code Examples

```python
import requests

url = "https://openrouter.ai/api/v1/chat/completions"

payload = {
    "model": "openai/gpt-3.5-turbo",
    "messages": [
        {
            "role": "user",
            "content": "What is the meaning of life?"
        }
    ]
}
headers = {
    "Authorization": "Bearer <token>",
    "Content-Type": "application/json"
}

response = requests.post(url, json=payload, headers=headers)

print(response.json())
```

```javascript
const url = 'https://openrouter.ai/api/v1/chat/completions';
const options = {
  method: 'POST',
  headers: {Authorization: 'Bearer <token>', 'Content-Type': 'application/json'},
  body: '{"model":"openai/gpt-3.5-turbo","messages":[{"role":"user","content":"What is the meaning of life?"}]}'
};

try {
  const response = await fetch(url, options);
  const data = await response.json();
  console.log(data);
} catch (error) {
  console.error(error);
}
```

```go
package main

import (
	"fmt"
	"strings"
	"net/http"
	"io"
)

func main() {

	url := "https://openrouter.ai/api/v1/chat/completions"

	payload := strings.NewReader("{\n  \"model\": \"openai/gpt-3.5-turbo\",\n  \"messages\": [\n    {\n      \"role\": \"user\",\n      \"content\": \"What is the meaning of life?\"\n    }\n  ]\n}")

	req, _ := http.NewRequest("POST", url, payload)

	req.Header.Add("Authorization", "Bearer <token>")
	req.Header.Add("Content-Type", "application/json")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := io.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

```ruby
require 'uri'
require 'net/http'

url = URI("https://openrouter.ai/api/v1/chat/completions")

http = Net::HTTP.new(url.host, url.port)
http.use_ssl = true

request = Net::HTTP::Post.new(url)
request["Authorization"] = 'Bearer <token>'
request["Content-Type"] = 'application/json'
request.body = "{\n  \"model\": \"openai/gpt-3.5-turbo\",\n  \"messages\": [\n    {\n      \"role\": \"user\",\n      \"content\": \"What is the meaning of life?\"\n    }\n  ]\n}"

response = http.request(request)
puts response.read_body
```

```java
HttpResponse<String> response = Unirest.post("https://openrouter.ai/api/v1/chat/completions")
  .header("Authorization", "Bearer <token>")
  .header("Content-Type", "application/json")
  .body("{\n  \"model\": \"openai/gpt-3.5-turbo\",\n  \"messages\": [\n    {\n      \"role\": \"user\",\n      \"content\": \"What is the meaning of life?\"\n    }\n  ]\n}")
  .asString();
```

```php
<?php

$client = new \GuzzleHttp\Client();

$response = $client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
  'body' => '{
  "model": "openai/gpt-3.5-turbo",
  "messages": [
    {
      "role": "user",
      "content": "What is the meaning of life?"
    }
  ]
}',
  'headers' => [
    'Authorization' => 'Bearer <token>',
    'Content-Type' => 'application/json',
  ],
]);

echo $response->getBody();
```

```csharp
var client = new RestClient("https://openrouter.ai/api/v1/chat/completions");
var request = new RestRequest(Method.POST);
request.AddHeader("Authorization", "Bearer <token>");
request.AddHeader("Content-Type", "application/json");
request.AddParameter("application/json", "{\n  \"model\": \"openai/gpt-3.5-turbo\",\n  \"messages\": [\n    {\n      \"role\": \"user\",\n      \"content\": \"What is the meaning of life?\"\n    }\n  ]\n}", ParameterType.RequestBody);
IRestResponse response = client.Execute(request);
```

```swift
import Foundation

let headers = [
  "Authorization": "Bearer <token>",
  "Content-Type": "application/json"
]
let parameters = [
  "model": "openai/gpt-3.5-turbo",
  "messages": [
    [
      "role": "user",
      "content": "What is the meaning of life?"
    ]
  ]
] as [String : Any]

let postData = JSONSerialization.data(withJSONObject: parameters, options: [])

let request = NSMutableURLRequest(url: NSURL(string: "https://openrouter.ai/api/v1/chat/completions")! as URL,
                                        cachePolicy: .useProtocolCachePolicy,
                                    timeoutInterval: 10.0)
request.httpMethod = "POST"
request.allHTTPHeaderFields = headers
request.httpBody = postData as Data

let session = URLSession.shared
let dataTask = session.dataTask(with: request as URLRequest, completionHandler: { (data, response, error) -> Void in
  if (error != nil) {
    print(error as Any)
  } else {
    let httpResponse = response as? HTTPURLResponse
    print(httpResponse)
  }
})

dataTask.resume()
```

