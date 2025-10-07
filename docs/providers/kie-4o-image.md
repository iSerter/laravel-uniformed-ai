# 4o Image API Quickstart

> Get started with the 4o Image API to generate high-quality AI images in minutes

## Welcome to 4o Image API

The 4o Image API, powered by the advanced GPT-4o model, provides high-quality AI image generation services. Whether you need text-to-image generation, image editing, or image variants, our API meets all your creative needs.

<CardGroup cols={2}>
  <Card title="Text-to-Image" icon="wand-magic-sparkles" href="/4o-image-api/generate-4-o-image">
    Generate high-quality images from text descriptions
  </Card>

  <Card title="Image Editing" icon="image" href="/4o-image-api/generate-4-o-image">
    Edit existing images using masks and prompts
  </Card>

  <Card title="Image Variants" icon="clone" href="/4o-image-api/generate-4-o-image">
    Generate multiple creative variants from input images
  </Card>

  <Card title="Task Management" icon="list-check" href="/4o-image-api/get-4-o-image-details">
    Track and monitor your image generation tasks
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
  curl -X POST "https://api.kie.ai/api/v1/gpt4o-image/generate" \
    -H "Authorization: Bearer YOUR_API_KEY" \
    -H "Content-Type: application/json" \
    -d '{
      "prompt": "A serene mountain landscape at sunset with a lake reflecting the orange sky, photorealistic style",
      "size": "1:1",
      "nVariants": 1
    }'
  ```

  ```javascript Node.js
  async function generateImage() {
    try {
      const response = await fetch('https://api.kie.ai/api/v1/gpt4o-image/generate', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer YOUR_API_KEY',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          prompt: 'A serene mountain landscape at sunset with a lake reflecting the orange sky, photorealistic style',
          size: '1:1',
          nVariants: 1
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
      url = "https://api.kie.ai/api/v1/gpt4o-image/generate"
      headers = {
          "Authorization": "Bearer YOUR_API_KEY",
          "Content-Type": "application/json"
      }
      
      payload = {
          "prompt": "A serene mountain landscape at sunset with a lake reflecting the orange sky, photorealistic style",
          "size": "1:1",
          "nVariants": 1
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
  curl -X GET "https://api.kie.ai/api/v1/gpt4o-image/record-info?taskId=YOUR_TASK_ID" \
    -H "Authorization: Bearer YOUR_API_KEY"
  ```

  ```javascript Node.js
  async function checkTaskStatus(taskId) {
    try {
      const response = await fetch(`https://api.kie.ai/api/v1/gpt4o-image/record-info?taskId=${taskId}`, {
        method: 'GET',
        headers: {
          'Authorization': 'Bearer YOUR_API_KEY'
        }
      });
      
      const result = await response.json();
      
      if (response.ok && result.code === 200) {
        const taskData = result.data;
        
        switch (taskData.successFlag) {
          case 1:
            console.log('Generation completed successfully!');
            console.log('Image URLs:', taskData.response.result_urls);
            return taskData.response;
            
          case 0:
            console.log('Still generating...');
            if (taskData.progress) {
              console.log(`Progress: ${(parseFloat(taskData.progress) * 100).toFixed(1)}%`);
            }
            return taskData.response;
            
          case 2:
            console.log('Generation failed');
            if (taskData.errorMessage) {
              console.error('Error message:', taskData.errorMessage);
            }
            return taskData.response;
            
          default:
            console.log('Unknown status:', taskData.successFlag);
            if (taskData.errorMessage) {
              console.error('Error message:', taskData.errorMessage);
            }
            return taskData.response;
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
  ```

  ```python Python
  import requests
  import time

  def check_task_status(task_id, api_key):
      url = f"https://api.kie.ai/api/v1/gpt4o-image/record-info?taskId={task_id}"
      headers = {"Authorization": f"Bearer {api_key}"}
      
      try:
          response = requests.get(url, headers=headers)
          result = response.json()
          
          if response.ok and result.get('code') == 200:
              task_data = result['data']
              success_flag = task_data['successFlag']
              
              if success_flag == 1:
                  print("Generation completed successfully!")
                  result_urls = task_data['response']['result_urls']
                  for i, url in enumerate(result_urls):
                      print(f"Image {i+1}: {url}")
                  return task_data
              elif success_flag == 0:
                  print("Still generating...")
                  if task_data.get('progress'):
                      progress = float(task_data['progress']) * 100
                      print(f"Progress: {progress:.1f}%")
                  return task_data
              elif success_flag == 2:
                  print("Generation failed")
                  if task_data.get('errorMessage'):
                      print(f"Error message: {task_data['errorMessage']}")
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
          if result and result.get('successFlag') in [1, 2]:  # Success or failed
              return result
          time.sleep(10)  # Wait 10 seconds before checking again
  ```
</CodeGroup>

### Response Format

**Successful Response:**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "taskId": "task_4o_abc123"
  }
}
```

**Task Status Response (Generating):**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "taskId": "task_4o_abc123",
    "paramJson": "{\"prompt\":\"A serene mountain landscape\",\"size\":\"1:1\"}",
    "completeTime": null,
    "response": null,
    "successFlag": 0,
    "errorCode": null,
    "errorMessage": null,
    "createTime": "2024-01-15 10:30:00",
    "progress": "0.50"
  }
}
```

**Task Status Response (Success):**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "taskId": "task_4o_abc123",
    "paramJson": "{\"prompt\":\"A serene mountain landscape\",\"size\":\"1:1\"}",
    "completeTime": "2024-01-15 10:35:00",
    "response": {
      "result_urls": [
        "https://example.com/generated-image.png"
      ]
    },
    "successFlag": 1,
    "errorCode": null,
    "errorMessage": null,
    "createTime": "2024-01-15 10:30:00",
    "progress": "1.00"
  }
}
```

**Task Status Response (Failed):**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "taskId": "task_4o_abc123",
    "paramJson": "{\"prompt\":\"A serene mountain landscape\",\"size\":\"1:1\"}",
    "completeTime": "2024-01-15 10:35:00",
    "response": {
      "result_urls": []
    },
    "successFlag": 2,
    "errorCode": 400,
    "errorMessage": "Generation failed, please try again or contact support",
    "createTime": "2024-01-15 10:30:00",
    "progress": "0.00"
  }
}
```

### Response Fields

<ResponseField name="successFlag" type="integer">
  Task status indicator:

  * `0`: Generating (in progress)
  * `1`: Success (completed successfully)
  * `2`: Failed (generation failed)
</ResponseField>

<ResponseField name="progress" type="string">
  Generation progress as a decimal string (0.00 to 1.00). Multiply by 100 for percentage.
</ResponseField>

<ResponseField name="createTime" type="string">
  Task creation timestamp in format "YYYY-MM-DD HH:mm:ss"
</ResponseField>

<ResponseField name="completeTime" type="string | null">
  Task completion timestamp in format "YYYY-MM-DD HH:mm:ss". Null if not yet completed.
</ResponseField>

## Core Features

### Text-to-Image

Generate high-quality images from text descriptions:

```json
{
  "prompt": "A cute orange cat sitting on a rainbow, cartoon style, bright colors",
  "size": "1:1",
  "nVariants": 2,
  "isEnhance": false
}
```

### Image Editing

Edit existing images using masks and prompts:

```json
{
  "filesUrl": ["https://example.com/original-image.jpg"],
  "maskUrl": "https://example.com/mask-image.png",
  "prompt": "Replace the sky with a starry night sky",
  "size": "3:2"
}
```

### Image Variants

Generate creative variants based on input images:

```json
{
  "filesUrl": ["https://example.com/base-image.jpg"],
  "prompt": "Keep main elements, change to watercolor painting style",
  "size": "2:3",
  "nVariants": 4
}
```

## Image Size Support

Three standard image ratios are supported:

<CardGroup cols={3}>
  <Card title="1:1" icon="square">
    **Square**

    Perfect for social media posts, avatars, product displays
  </Card>

  <Card title="3:2" icon="rectangle-wide">
    **Landscape**

    Ideal for landscape photos, desktop wallpapers, banners
  </Card>

  <Card title="2:3" icon="rectangle-vertical">
    **Portrait**

    Great for portraits, mobile wallpapers, poster designs
  </Card>
</CardGroup>

## Key Parameters

<ParamField path="prompt" type="string">
  Text description for image generation. Provide detailed, specific descriptions for better results.

  **Prompt Tips:**

  * Describe main objects and scenes
  * Specify artistic styles (e.g., "photorealistic", "cartoon", "watercolor")
  * Add color and lighting descriptions
  * Include mood and atmosphere elements
</ParamField>

<ParamField path="size" type="string" required>
  Image aspect ratio, required parameter:

  * `1:1` - Square
  * `3:2` - Landscape
  * `2:3` - Portrait
</ParamField>

<ParamField path="filesUrl" type="array">
  Input image URL list, supports up to 5 images. Supported formats: `.jpg`, `.jpeg`, `.png`, `.webp`, `.jfif`
</ParamField>

<ParamField path="maskUrl" type="string">
  Mask image URL to specify areas for editing. Black areas will be modified, white areas remain unchanged.
</ParamField>

<ParamField path="nVariants" type="integer">
  Number of image variants to generate. Options: 1, 2, or 4. Default is 1.
</ParamField>

<ParamField path="isEnhance" type="boolean">
  Prompt enhancement option. For specific scenarios like 3D image generation, enabling this can achieve more refined effects. Default is false.
</ParamField>

<ParamField path="enableFallback" type="boolean">
  Enable fallback mechanism. Automatically switches to backup models when the main model is unavailable. Default is false.
</ParamField>

## Complete Workflow Example

Here's a complete example for image generation and editing:

<Tabs>
  <Tab title="JavaScript">
    ```javascript
    class FourOImageAPI {
      constructor(apiKey) {
        this.apiKey = apiKey;
        this.baseUrl = 'https://api.kie.ai/api/v1/gpt4o-image';
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
      
      async waitForCompletion(taskId, maxWaitTime = 300000) { // 5 minutes max
        const startTime = Date.now();
        
        while (Date.now() - startTime < maxWaitTime) {
          const status = await this.getTaskStatus(taskId);
          
          switch (status.successFlag) {
            case 1:
              console.log('Generation completed successfully!');
              return status.response;
              
            case 0:
              console.log('Still generating...');
              if (status.progress) {
                console.log(`Progress: ${(parseFloat(status.progress) * 100).toFixed(1)}%`);
              }
              break;
              
            case 2:
              const errorMsg = status.errorMessage || 'Generation failed';
              console.error('Error message:', errorMsg);
              throw new Error(errorMsg);
              
            default:
              console.log('Unknown status:', status.successFlag);
              if (status.errorMessage) {
                console.error('Error message:', status.errorMessage);
              }
              break;
          }
          
          // Wait 10 seconds before next check
          await new Promise(resolve => setTimeout(resolve, 10000));
        }
        
        throw new Error('Generation timeout');
      }
      
      async getTaskStatus(taskId) {
        const response = await fetch(`${this.baseUrl}/record-info?taskId=${taskId}`, {
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
      
      async getDownloadUrl(imageUrl) {
        const response = await fetch(`${this.baseUrl}/download-url`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${this.apiKey}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ imageUrl })
        });
        
        const result = await response.json();
        if (!response.ok || result.code !== 200) {
          throw new Error(`Get download URL failed: ${result.msg || 'Unknown error'}`);
        }
        
        return result.data.downloadUrl;
      }
    }

    // Usage Example
    async function main() {
      const api = new FourOImageAPI('YOUR_API_KEY');
      
      try {
        // Text-to-Image Generation
        console.log('Starting image generation...');
        const taskId = await api.generateImage({
          prompt: 'A futuristic cityscape with flying cars and neon lights, cyberpunk style',
          size: '1:1',
          nVariants: 2,
          isEnhance: true,
          enableFallback: true
        });
        
        // Wait for completion
        console.log(`Task ID: ${taskId}. Waiting for completion...`);
        const result = await api.waitForCompletion(taskId);
        
        console.log('Image generation successful!');
        result.result_urls.forEach((url, index) => {
          console.log(`Image ${index + 1}: ${url}`);
        });
        
        // Get download URL
        const downloadUrl = await api.getDownloadUrl(result.result_urls[0]);
        console.log('Download URL:', downloadUrl);
        
        // Image Editing Example
        console.log('\nStarting image editing...');
        const editTaskId = await api.generateImage({
          filesUrl: [result.result_urls[0]],
          prompt: 'Add beautiful rainbow in the sky',
          size: '3:2'
        });
        
        const editResult = await api.waitForCompletion(editTaskId);
        console.log('Image editing successful!');
        console.log('Edited image:', editResult.result_urls[0]);
        
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

    class FourOImageAPI:
        def __init__(self, api_key):
            self.api_key = api_key
            self.base_url = 'https://api.kie.ai/api/v1/gpt4o-image'
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
        
        def wait_for_completion(self, task_id, max_wait_time=300):
            start_time = time.time()
            
            while time.time() - start_time < max_wait_time:
                status = self.get_task_status(task_id)
                
                if status['successFlag'] == 1:
                    print("Generation completed successfully!")
                    return status['response']
                elif status['successFlag'] == 0:
                    print("Still generating...")
                    if status.get('progress'):
                        progress = float(status['progress']) * 100
                        print(f"Progress: {progress:.1f}%")
                elif status['successFlag'] == 2:
                    error_msg = status.get('errorMessage', 'Generation failed')
                    print(f"Error message: {error_msg}")
                    raise Exception(error_msg)
                else:
                    print(f"Unknown status: {status['successFlag']}")
                    if status.get('errorMessage'):
                        print(f"Error message: {status['errorMessage']}")
                
                time.sleep(10)  # Wait 10 seconds
            
            raise Exception('Generation timeout')
        
        def get_task_status(self, task_id):
            response = requests.get(f'{self.base_url}/record-info?taskId={task_id}',
                                  headers={'Authorization': f'Bearer {self.api_key}'})
            result = response.json()
            
            if not response.ok or result.get('code') != 200:
                raise Exception(f"Status check failed: {result.get('msg', 'Unknown error')}")
            
            return result['data']
        
        def get_download_url(self, image_url):
            response = requests.post(f'{self.base_url}/download-url',
                                   headers=self.headers, 
                                   json={'imageUrl': image_url})
            result = response.json()
            
            if not response.ok or result.get('code') != 200:
                raise Exception(f"Get download URL failed: {result.get('msg', 'Unknown error')}")
            
            return result['data']['downloadUrl']

    # Usage Example
    def main():
        api = FourOImageAPI('YOUR_API_KEY')
        
        try:
            # Text-to-Image Generation
            print('Starting image generation...')
            task_id = api.generate_image(
                prompt='A futuristic cityscape with flying cars and neon lights, cyberpunk style',
                size='1:1',
                nVariants=2,
                isEnhance=True,
                enableFallback=True
            )
            
            # Wait for completion
            print(f'Task ID: {task_id}. Waiting for completion...')
            result = api.wait_for_completion(task_id)
            
            print('Image generation successful!')
            for i, url in enumerate(result['result_urls']):
                print(f'Image {i + 1}: {url}')
            
            # Get download URL
            download_url = api.get_download_url(result['result_urls'][0])
            print(f'Download URL: {download_url}')
            
            # Image Editing Example
            print('\nStarting image editing...')
            edit_task_id = api.generate_image(
                filesUrl=[result['result_urls'][0]],
                prompt='Add beautiful rainbow in the sky',
                size='3:2'
            )
            
            edit_result = api.wait_for_completion(edit_task_id)
            print('Image editing successful!')
            print(f'Edited image: {edit_result["result_urls"][0]}')
            
        except Exception as error:
            print(f'Error: {error}')

    if __name__ == '__main__':
        main()
    ```
  </Tab>
</Tabs>

## Advanced Features

### Mask Editing

Use masks for precise image editing:

```javascript
const editTaskId = await api.generateImage({
  filesUrl: ['https://example.com/original.jpg'],
  maskUrl: 'https://example.com/mask.png',
  prompt: 'Replace the masked area with a beautiful garden',
  size: '3:2'
});
```

<Info>
  Black areas in the mask image will be edited, white areas remain unchanged. The mask must match the original image dimensions.
</Info>

### Fallback Mechanism

Enable fallback mechanism for service reliability:

```javascript
const taskId = await api.generateImage({
  prompt: 'Artistic concept design',
  size: '1:1',
  enableFallback: true,
  fallbackModel: 'FLUX_MAX' // or 'GPT_IMAGE_1'
});
```

### Using Callbacks

Set up webhook callbacks for automatic notifications:

```javascript
const taskId = await api.generateImage({
  prompt: 'Digital artwork',
  size: '1:1',
  callBackUrl: 'https://your-server.com/4o-callback'
});

// Your callback endpoint will receive:
app.post('/4o-callback', (req, res) => {
  const { code, data } = req.body;
  
  if (code === 200) {
    console.log('Images ready:', data.info.result_urls);
  } else {
    console.log('Generation failed:', req.body.msg);
  }
  
  res.status(200).json({ status: 'received' });
});
```

<Card title="Learn More About Callbacks" icon="webhook" href="/4o-image-api/generate-4-o-image-callbacks">
  Set up webhook callbacks to receive automatic notifications when your images are ready.
</Card>

## Task Status Descriptions

<ResponseField name="successFlag: 0" type="In Progress">
  Task is currently being processed. Check `progress` field for completion percentage.
</ResponseField>

<ResponseField name="successFlag: 1" type="Success">
  Task completed successfully. Generated images are available in `response.result_urls`.
</ResponseField>

<ResponseField name="successFlag: 2" type="Failed">
  Image generation failed. Check `errorMessage` field for details.
</ResponseField>

## Best Practices

<AccordionGroup>
  <Accordion title="Prompt Optimization">
    * Use detailed, specific descriptions
    * Include style and technique descriptions (e.g., "photorealistic", "impressionist", "digital art")
    * Specify color, lighting, and composition requirements
    * Avoid overly complex or contradictory descriptions
  </Accordion>

  <Accordion title="Image Quality">
    * Choose appropriate aspect ratios for your use case
    * Consider enabling prompt enhancement for complex scenes
    * Use high-quality input images for editing
    * Ensure mask images accurately mark editing areas
  </Accordion>

  <Accordion title="Performance Optimization">
    * Use callbacks instead of frequent polling
    * Enable fallback mechanism for service reliability
    * Set appropriate variant counts to balance quality and cost
    * Download images promptly to avoid 14-day expiration
  </Accordion>

  <Accordion title="Error Handling">
    * Monitor all task states (`successFlag` values 0, 1, 2)
    * Check `errorMessage` field when `successFlag` is 2
    * Display progress information during generation (`progress` field)
    * Implement proper retry logic for failed requests
    * Verify input image accessibility before submission
    * Log error information for debugging and support
  </Accordion>
</AccordionGroup>

## Image Storage and Downloads

<Warning>
  Generated images are stored for **14 days** before automatic deletion. Download URLs are valid for **20 minutes**.
</Warning>

* Image URLs remain accessible for 14 days after generation
* Use download URL API to solve cross-domain download issues
* Download URLs expire after 20 minutes
* Recommended to download and store important images locally

## Next Steps

<CardGroup cols={2}>
  <Card title="Generate Images" icon="image" href="/4o-image-api/generate-4-o-image">
    Complete API reference for image generation
  </Card>

  <Card title="Task Details" icon="magnifying-glass" href="/4o-image-api/get-4-o-image-details">
    Query and monitor task status
  </Card>

  <Card title="Download URL" icon="download" href="/4o-image-api/get-4-o-image-download-url">
    Get direct download URLs
  </Card>

  <Card title="Callback Setup" icon="webhook" href="/4o-image-api/generate-4-o-image-callbacks">
    Set up automatic notification callbacks
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

Ready to start creating amazing AI images? [Get your API key](https://kie.ai/api-key) and begin creating today!
