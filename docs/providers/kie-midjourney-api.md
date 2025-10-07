# Midjourney API Quickstart

> Get started with the Midjourney API to generate stunning AI images in minutes

## Welcome to Midjourney API

The Midjourney API enables you to generate high-quality AI images using the power of Midjourney's advanced AI models. Whether you're building an app, automating workflows, or creating content, our API provides simple and reliable access to AI image generation.

<CardGroup cols={2}>
  <Card title="Text-to-Image" icon="wand-magic-sparkles" href="/mj-api/generate-mj-image">
    Transform text prompts into stunning visual artwork
  </Card>

  <Card title="Image-to-Image" icon="image" href="/mj-api/generate-mj-image">
    Use existing images as a foundation for new creations
  </Card>

  <Card title="Image-to-Video" icon="video" href="/mj-api/generate-mj-image">
    Convert static images into dynamic video content
  </Card>

  <Card title="Image Upscaling" icon="magnifying-glass-plus" href="/mj-api/upscale">
    Enhance image resolution and quality
  </Card>

  <Card title="Image Variations" icon="palette" href="/mj-api/vary">
    Create variations with enhanced clarity and style
  </Card>

  <Card title="Task Management" icon="list-check" href="/mj-api/get-mj-task-details">
    Track and monitor your generation tasks
  </Card>
</CardGroup>

## Authentication

All API requests require authentication using a Bearer token. Get your API key from the [API Key Management Page](https://kie.ai/api-key).

<Warning>
  Keep your API key secure and never share it publicly. If compromised, reset it immediately.
</Warning>

### API Base URL

```
https://api.kie.ai
```

### Authentication Header

```http
Authorization: Bearer YOUR_API_KEY
```

## Quick Start Guide

### Step 1: Generate Your First Image

Start with a simple text-to-image generation request:

<CodeGroup>
  ```bash cURL
  curl -X POST "https://api.kie.ai/api/v1/mj/generate" \
    -H "Authorization: Bearer YOUR_API_KEY" \
    -H "Content-Type: application/json" \
    -d '{
      "taskType": "mj_txt2img",
      "prompt": "A majestic mountain landscape at sunset with snow-capped peaks",
      "speed": "relaxed",
      "aspectRatio": "16:9",
      "version": "7"
    }'
  ```

  ```javascript Node.js
  async function generateImage() {
    try {
      const response = await fetch('https://api.kie.ai/api/v1/mj/generate', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer YOUR_API_KEY',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          taskType: 'mj_txt2img',
          prompt: 'A majestic mountain landscape at sunset with snow-capped peaks',
          speed: 'relaxed',
          aspectRatio: '16:9',
          version: '7'
        })
      });
      
      const data = await response.json();
      
      if (response.ok && data.code === 200) {
        console.log('Task submitted:', data);
        console.log('Task ID:', data.data.taskId);
        return data.data.taskId;
      } else {
        console.error('Request failed:', data.msg || 'Unknown error');
        return null;
      }
    } catch (error) {
      console.error('Error:', error.message);
      return null;
    }
  }

  generateImage();
  ```

  ```python Python
  import requests

  def generate_image():
      url = "https://api.kie.ai/api/v1/mj/generate"
      headers = {
          "Authorization": "Bearer YOUR_API_KEY",
          "Content-Type": "application/json"
      }
      
      payload = {
          "taskType": "mj_txt2img",
          "prompt": "A majestic mountain landscape at sunset with snow-capped peaks",
          "speed": "relaxed",
          "aspectRatio": "16:9",
          "version": "7"
      }
      
      try:
          response = requests.post(url, json=payload, headers=headers)
          result = response.json()
          
          if response.ok and result.get('code') == 200:
              print(f"Task submitted: {result}")
              print(f"Task ID: {result['data']['taskId']}")
              return result['data']['taskId']
          else:
              print(f"Request failed: {result.get('msg', 'Unknown error')}")
              return None
      except requests.exceptions.RequestException as e:
          print(f"Error: {e}")
          return None

  generate_image()
  ```
</CodeGroup>

### Step 2: Check Task Status

Use the returned task ID to check the generation status:

<CodeGroup>
  ```bash cURL
  curl -X GET "https://api.kie.ai/api/v1/mj/record-info?taskId=YOUR_TASK_ID" \
    -H "Authorization: Bearer YOUR_API_KEY"
  ```

  ```javascript Node.js
  async function checkTaskStatus(taskId) {
    try {
      const response = await fetch(`https://api.kie.ai/api/v1/mj/record-info?taskId=${taskId}`, {
        method: 'GET',
        headers: {
          'Authorization': 'Bearer YOUR_API_KEY'
        }
      });
      
      const result = await response.json();
      
      if (response.ok && result.code === 200) {
        const taskData = result.data;
        
        switch (taskData.successFlag) {
          case 0:
            console.log('Task is generating...');
            console.log('Create time:', taskData.createTime);
            return taskData;
            
          case 1:
            console.log('Task generation completed!');
            console.log('Result URLs:', taskData.resultInfoJson?.resultUrls);
            console.log('Complete time:', taskData.completeTime);
            return taskData;
            
          case 2:
            console.log('Task generation failed');
            if (taskData.errorMessage) {
              console.error('Error message:', taskData.errorMessage);
            }
            if (taskData.errorCode) {
              console.error('Error code:', taskData.errorCode);
            }
            return taskData;
            
          case 3:
            console.log('Task created successfully but generation failed');
            if (taskData.errorMessage) {
              console.error('Error message:', taskData.errorMessage);
            }
            if (taskData.errorCode) {
              console.error('Error code:', taskData.errorCode);
            }
            return taskData;
            
          default:
            console.log('Unknown status:', taskData.successFlag);
            if (taskData.errorMessage) {
              console.error('Error message:', taskData.errorMessage);
            }
            return taskData;
        }
      } else {
        console.error('Query failed:', result.msg || 'Unknown error');
        return null;
      }
    } catch (error) {
      console.error('Status check failed:', error.message);
      return null;
    }
  }

  // Usage
  const status = await checkTaskStatus('YOUR_TASK_ID');
  ```

  ```python Python
  import requests
  import time

  def check_task_status(task_id, api_key):
      url = f"https://api.kie.ai/api/v1/mj/record-info?taskId={task_id}"
      headers = {"Authorization": f"Bearer {api_key}"}
      
      try:
          response = requests.get(url, headers=headers)
          result = response.json()
          
          if response.ok and result.get('code') == 200:
              task_data = result['data']
              success_flag = task_data['successFlag']
              
              if success_flag == 0:
                  print("Task is generating...")
                  print(f"Create time: {task_data.get('createTime', '')}")
                  return task_data
              elif success_flag == 1:
                  print("Task generation completed!")
                  result_urls = task_data.get('resultInfoJson', {}).get('resultUrls', [])
                  for i, url_info in enumerate(result_urls):
                      print(f"Image {i+1}: {url_info.get('resultUrl', '')}")
                  print(f"Complete time: {task_data.get('completeTime', '')}")
                  return task_data
              elif success_flag == 2:
                  print("Task generation failed")
                  if task_data.get('errorMessage'):
                      print(f"Error message: {task_data['errorMessage']}")
                  if task_data.get('errorCode'):
                      print(f"Error code: {task_data['errorCode']}")
                  return task_data
              elif success_flag == 3:
                  print("Task created successfully but generation failed")
                  if task_data.get('errorMessage'):
                      print(f"Error message: {task_data['errorMessage']}")
                  if task_data.get('errorCode'):
                      print(f"Error code: {task_data['errorCode']}")
                  return task_data
              else:
                  print(f"Unknown status: {success_flag}")
                  if task_data.get('errorMessage'):
                      print(f"Error message: {task_data['errorMessage']}")
                  return task_data
          else:
              print(f"Query failed: {result.get('msg', 'Unknown error')}")
              return None
      except requests.exceptions.RequestException as e:
          print(f"Status check failed: {e}")
          return None

  # Poll until completion
  def wait_for_completion(task_id, api_key):
      while True:
          result = check_task_status(task_id, api_key)
          if result and result.get('successFlag') in [1, 2, 3]:  # Final states (success or failure)
              return result
          time.sleep(30)  # Wait 30 seconds before checking again
  ```
</CodeGroup>

### Response Format

**Successful Response:**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "taskId": "mj_task_abcdef123456"
  }
}
```

**Task Status Response:**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "taskId": "mj_task_abcdef123456",
    "successFlag": 1,
    "resultInfoJson": {
      "resultUrls": [
        {"resultUrl": "https://example.com/image1.jpg"},
        {"resultUrl": "https://example.com/image2.jpg"},
        {"resultUrl": "https://example.com/image3.jpg"},
        {"resultUrl": "https://example.com/image4.jpg"}
      ]
    }
  }
}
```

## Generation Types

<Tabs>
  <Tab title="Text-to-Image">
    Generate images from text descriptions:

    ```json
    {
      "taskType": "mj_txt2img",
      "prompt": "A futuristic cityscape with flying cars and neon lights",
      "aspectRatio": "16:9",
      "version": "7"
    }
    ```
  </Tab>

  <Tab title="Image-to-Image">
    Transform existing images with text prompts:

    ```json
    {
      "taskType": "mj_img2img",
      "prompt": "Transform this into a cyberpunk style",
      "fileUrl": "https://example.com/source-image.jpg",
      "aspectRatio": "1:1",
      "version": "7"
    }
    ```
  </Tab>

  <Tab title="Image-to-Video">
    Create videos from static images:

    ```json
    {
      "taskType": "mj_video",
      "prompt": "Add gentle movement and atmospheric effects",
      "fileUrl": "https://example.com/source-image.jpg",
      "version": "7"
    }
    ```
  </Tab>
</Tabs>

## Generation Speeds

Choose the right speed for your needs:

<CardGroup cols={3}>
  <Card title="Relaxed" icon="turtle">
    **Free tier option**

    Slower generation but cost-effective for non-urgent tasks
  </Card>

  <Card title="Fast" icon="rabbit">
    **Balanced option**

    Standard generation speed for most use cases
  </Card>

  <Card title="Turbo" icon="rocket">
    **Premium speed**

    Fastest generation for time-critical applications
  </Card>
</CardGroup>

## Key Parameters

<ParamField path="prompt" type="string" required>
  Text description of the desired image. Be specific and descriptive for best results.

  **Tips for better prompts:**

  * Include style descriptors (e.g., "photorealistic", "watercolor", "digital art")
  * Specify composition details (e.g., "close-up", "wide angle", "bird's eye view")
  * Add lighting information (e.g., "golden hour", "dramatic lighting", "soft natural light")
</ParamField>

<ParamField path="aspectRatio" type="string">
  Output image aspect ratio. Choose from:

  * `1:1` - Square (social media)
  * `16:9` - Widescreen (wallpapers, presentations)
  * `9:16` - Portrait (mobile wallpapers)
  * `4:3` - Standard (traditional displays)
  * And 7 other ratios
</ParamField>

<ParamField path="version" type="string">
  Midjourney model version:

  * `7` - Latest model (recommended)
  * `6.1`, `6` - Previous versions
  * `niji6` - Anime/illustration focused
</ParamField>

<ParamField path="stylization" type="integer">
  Artistic style intensity (0-1000):

  * Low values (0-100): More realistic
  * High values (500-1000): More artistic/stylized
</ParamField>

## Complete Workflow Example

Here's a complete example that generates an image and waits for completion:

<Tabs>
  <Tab title="JavaScript">
    ```javascript
    class MidjourneyAPI {
      constructor(apiKey) {
        this.apiKey = apiKey;
        this.baseUrl = 'https://api.kie.ai/api/v1/mj';
      }
      
      async generateImage(options) {
        const response = await fetch(`${this.baseUrl}/generate`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${this.apiKey}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(options)
        });
        
        const result = await response.json();
        if (!response.ok || result.code !== 200) {
          throw new Error(`Generation failed: ${result.msg || 'Unknown error'}`);
        }
        
        return result.data.taskId;
      }
      
      async waitForCompletion(taskId, maxWaitTime = 600000) { // Max wait 10 minutes
        const startTime = Date.now();
        
        while (Date.now() - startTime < maxWaitTime) {
          const status = await this.getTaskStatus(taskId);
          
          switch (status.successFlag) {
            case 0:
              console.log('Task is generating, continue waiting...');
              break;
              
            case 1:
              console.log('Generation completed successfully!');
              return status.resultInfoJson;
              
            case 2:
              const taskError = status.errorMessage || 'Task generation failed';
              console.error('Task generation failed:', taskError);
              if (status.errorCode) {
                console.error('Error code:', status.errorCode);
              }
              throw new Error(taskError);
              
            case 3:
              const generateError = status.errorMessage || 'Task created successfully but generation failed';
              console.error('Generation failed:', generateError);
              if (status.errorCode) {
                console.error('Error code:', status.errorCode);
              }
              throw new Error(generateError);
              
            default:
              console.log(`Unknown status: ${status.successFlag}`);
              if (status.errorMessage) {
                console.error('Error message:', status.errorMessage);
              }
              break;
          }
          
          // Wait 30 seconds before checking again
          await new Promise(resolve => setTimeout(resolve, 30000));
        }
        
        throw new Error('Generation timeout');
      }
      
      async getTaskStatus(taskId) {
        const response = await fetch(`${this.baseUrl}/record-info?taskId=${taskId}`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${this.apiKey}`
          }
        });
        
        const result = await response.json();
        if (!response.ok || result.code !== 200) {
          throw new Error(`Status check failed: ${result.msg || 'Unknown error'}`);
        }
        
        return result.data;
      }
      
      async upscaleImage(taskId, index) {
        const response = await fetch(`${this.baseUrl}/upscale`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${this.apiKey}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            taskId,
            index
          })
        });
        
        const result = await response.json();
        if (!response.ok || result.code !== 200) {
          throw new Error(`Upscale failed: ${result.msg || 'Unknown error'}`);
        }
        
        return result.data.taskId;
      }
    }

    // Usage example
    async function main() {
      const api = new MidjourneyAPI('YOUR_API_KEY');
      
      try {
        // Text-to-image generation
        console.log('Starting image generation...');
        const taskId = await api.generateImage({
          taskType: 'mj_txt2img',
          prompt: 'A majestic ancient castle perched on a misty mountain peak, golden sunset light illuminating the stone walls',
          speed: 'fast',
          aspectRatio: '16:9',
          version: '7',
          stylization: 500
        });
        
        // Wait for completion
        console.log(`Task ID: ${taskId}. Waiting for completion...`);
        const result = await api.waitForCompletion(taskId);
        
        console.log('Image generation successful!');
        console.log('Generated images count:', result.resultUrls.length);
        result.resultUrls.forEach((urlInfo, index) => {
          console.log(`Image ${index + 1}: ${urlInfo.resultUrl}`);
        });
        
        // Upscale the first image
        console.log('\nStarting upscale of first image...');
        const upscaleTaskId = await api.upscaleImage(taskId, 1);
        
        const upscaleResult = await api.waitForCompletion(upscaleTaskId);
        console.log('Image upscale successful!');
        console.log('Upscaled image:', upscaleResult.resultUrls[0].resultUrl);
        
      } catch (error) {
        console.error('Error:', error.message);
      }
    }

    main();
    ```
  </Tab>

  <Tab title="Python">
    ```python
    import requests
    import time

    class MidjourneyAPI:
        def __init__(self, api_key):
            self.api_key = api_key
            self.base_url = 'https://api.kie.ai/api/v1/mj'
            self.headers = {
                'Authorization': f'Bearer {api_key}',
                'Content-Type': 'application/json'
            }
        
        def generate_image(self, **options):
            response = requests.post(f'{self.base_url}/generate', 
                                   headers=self.headers, json=options)
            result = response.json()
            
            if not response.ok or result.get('code') != 200:
                raise Exception(f"Generation failed: {result.get('msg', 'Unknown error')}")
            
            return result['data']['taskId']
        
        def wait_for_completion(self, task_id, max_wait_time=600):
            start_time = time.time()
            
            while time.time() - start_time < max_wait_time:
                status = self.get_task_status(task_id)
                success_flag = status['successFlag']
                
                if success_flag == 0:
                    print("Task is generating, continue waiting...")
                elif success_flag == 1:
                    print("Generation completed successfully!")
                    return status['resultInfoJson']
                elif success_flag == 2:
                    task_error = status.get('errorMessage', 'Task generation failed')
                    print(f"Task generation failed: {task_error}")
                    if status.get('errorCode'):
                        print(f"Error code: {status['errorCode']}")
                    raise Exception(task_error)
                elif success_flag == 3:
                    generate_error = status.get('errorMessage', 'Task created successfully but generation failed')
                    print(f"Generation failed: {generate_error}")
                    if status.get('errorCode'):
                        print(f"Error code: {status['errorCode']}")
                    raise Exception(generate_error)
                else:
                    print(f"Unknown status: {success_flag}")
                    if status.get('errorMessage'):
                        print(f"Error message: {status['errorMessage']}")
                
                time.sleep(30)  # Wait 30 seconds
            
            raise Exception('Generation timeout')
        
        def get_task_status(self, task_id):
            response = requests.get(f'{self.base_url}/record-info?taskId={task_id}',
                                  headers={'Authorization': f'Bearer {self.api_key}'})
            result = response.json()
            
            if not response.ok or result.get('code') != 200:
                raise Exception(f"Status check failed: {result.get('msg', 'Unknown error')}")
            
            return result['data']
        
        def upscale_image(self, task_id, index):
            data = {
                'taskId': task_id,
                'index': index
            }
            
            response = requests.post(f'{self.base_url}/upscale', 
                                   headers=self.headers, json=data)
            result = response.json()
            
            if not response.ok or result.get('code') != 200:
                raise Exception(f"Upscale failed: {result.get('msg', 'Unknown error')}")
            
            return result['data']['taskId']

    # Usage example
    def main():
        api = MidjourneyAPI('YOUR_API_KEY')
        
        try:
            # Text-to-image generation
            print('Starting image generation...')
            task_id = api.generate_image(
                taskType='mj_txt2img',
                prompt='A majestic ancient castle perched on a misty mountain peak, golden sunset light illuminating the stone walls',
                speed='fast',
                aspectRatio='16:9',
                version='7',
                stylization=500
            )
            
            # Wait for completion
            print(f'Task ID: {task_id}. Waiting for completion...')
            result = api.wait_for_completion(task_id)
            
            print('Image generation successful!')
            print(f'Generated images count: {len(result["resultUrls"])}')
            for i, url_info in enumerate(result['resultUrls']):
                print(f'Image {i + 1}: {url_info["resultUrl"]}')
            
            # Upscale the first image
            print('\nStarting upscale of first image...')
            upscale_task_id = api.upscale_image(task_id, 1)
            
            upscale_result = api.wait_for_completion(upscale_task_id)
            print('Image upscale successful!')
            print(f'Upscaled image: {upscale_result["resultUrls"][0]["resultUrl"]}')
            
        except Exception as error:
            print(f'Error: {error}')

    if __name__ == '__main__':
        main()
    ```
  </Tab>
</Tabs>

## Async Processing with Callbacks

For production applications, use callbacks instead of polling:

```json
{
  "taskType": "mj_txt2img",
  "prompt": "A serene zen garden with cherry blossoms",
  "callBackUrl": "https://your-app.com/webhook/mj-callback",
  "aspectRatio": "16:9"
}
```

The system will POST results to your callback URL when generation completes.

<Card title="Learn More About Callbacks" icon="webhook" href="/mj-api/generate-mj-image-callbacks">
  Complete guide to implementing and handling Midjourney API callbacks
</Card>

## Best Practices

<AccordionGroup>
  <Accordion title="Prompt Engineering">
    * Be specific and descriptive in your prompts
    * Include style, mood, and composition details
    * Use artistic references when appropriate
    * Test different prompt variations to find what works best
  </Accordion>

  <Accordion title="Performance Optimization">
    * Use callbacks instead of frequent polling
    * Implement proper error handling and retry logic
    * Cache results when possible
    * Choose appropriate generation speed for your use case
  </Accordion>

  <Accordion title="Cost Management">
    * Use "relaxed" speed for non-urgent tasks
    * Monitor your credit usage regularly
    * Implement request batching where possible
    * Set up usage alerts in your application
  </Accordion>

  <Accordion title="Error Handling">
    * Always check the response status code
    * Implement exponential backoff for retries
    * Handle rate limiting gracefully
    * Log errors for debugging and monitoring
  </Accordion>
</AccordionGroup>

## Status Codes

<ResponseField name="200" type="Success">
  Task created successfully or request completed
</ResponseField>

<ResponseField name="400" type="Bad Request">
  Invalid request parameters or malformed JSON
</ResponseField>

<ResponseField name="401" type="Unauthorized">
  Missing or invalid API key
</ResponseField>

<ResponseField name="402" type="Insufficient Credits">
  Account doesn't have enough credits for the operation
</ResponseField>

<ResponseField name="429" type="Rate Limited">
  Too many requests - implement backoff strategy
</ResponseField>

<ResponseField name="500" type="Server Error">
  Internal server error - contact support if persistent
</ResponseField>

## Task Status Descriptions

<ResponseField name="successFlag: 0" type="Generating">
  Task is currently being processed
</ResponseField>

<ResponseField name="successFlag: 1" type="Success">
  Task completed successfully
</ResponseField>

<ResponseField name="successFlag: 2" type="Failed">
  Task generation failed
</ResponseField>

<ResponseField name="successFlag: 3" type="Generation Failed">
  Task created successfully but generation failed
</ResponseField>

## Image Storage and Retention

<Warning>
  Generated image files are retained for **15 days** before deletion. Please download and save your images within this timeframe.
</Warning>

* Image URLs remain accessible for 15 days after generation
* Plan your workflows to download or process images before expiration
* Consider implementing automated download systems for production use

## Next Steps

<CardGroup cols={2}>
  <Card title="Generate Images" icon="image" href="/mj-api/generate-mj-image">
    Complete API reference for image generation
  </Card>

  <Card title="Callback Setup" icon="webhook" href="/mj-api/generate-mj-image-callbacks">
    Implement webhooks for async processing
  </Card>

  <Card title="Task Details" icon="magnifying-glass" href="/mj-api/get-mj-task-details">
    Query and monitor task status
  </Card>

  <Card title="Account Credits" icon="coins" href="/common-api/get-account-credits">
    Monitor your API usage and credits
  </Card>
</CardGroup>

## Support

<Info>
  Need help? Our technical support team is here to assist you.

  * **Email**: [support@kie.ai](mailto:support@kie.ai)
  * **Documentation**: [docs.kie.ai](https://docs.kie.ai)
  * **API Status**: Check our status page for real-time API health
</Info>

***

Ready to start generating amazing AI images? [Get your API key](https://kie.ai/api-key) and begin creating today!
