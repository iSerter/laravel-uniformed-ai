# Veo3 API Quickstart

> Get started with Veo3 API in 5 minutes

Welcome to Veo3 API! This guide will help you quickly get started with our high-quality AI video generation service.

## Overview

Veo3 API is a powerful AI video generation platform that supports:

<CardGroup cols={2}>
  <Card title="Text-to-Video" icon="text" href="/veo3-api/generate-veo-3-video">
    Generate high-quality videos through descriptive text prompts
  </Card>

  <Card title="Image-to-Video" icon="image" href="/veo3-api/generate-veo-3-video">
    Bring static images to life, creating engaging videos
  </Card>

  <Card title="HD Support" icon="video" href="/veo3-api/get-veo-3-1080-p-video">
    Support for generating 1080P high-definition videos (16:9 aspect ratio)
  </Card>

  <Card title="Real-time Callbacks" icon="bell" href="/veo3-api/generate-veo-3-video-callbacks">
    Automatically push results to your server when tasks complete
  </Card>
</CardGroup>

## Step 1: Get Your API Key

1. Visit [API Key Management Page](https://kie.ai/api-key)
2. Register or log in to your account
3. Generate a new API Key
4. Safely store your API Key

<Warning>
  Please keep your API Key secure and do not expose it in public code repositories. If you suspect it has been compromised, reset it immediately.
</Warning>

## Step 2: Basic Authentication

All API requests need to include your API Key in the request headers:

```http
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

**API Base URL**: `https://api.kie.ai`

## Step 3: Your First Video Generation

### Text-to-Video Example

<CodeGroup>
  ```javascript Node.js
  async function generateVideo() {
    try {
      const response = await fetch('https://api.kie.ai/api/v1/veo/generate', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer YOUR_API_KEY',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          prompt: "A cute cat playing in a garden on a sunny day, high quality",
          model: "veo3",
          aspectRatio: "16:9",
          callBackUrl: "https://your-website.com/callback" // Optional
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

  generateVideo();
  ```

  ```python Python
  import requests
  import json

  def generate_video():
      url = "https://api.kie.ai/api/v1/veo/generate"
      headers = {
          "Authorization": "Bearer YOUR_API_KEY",
          "Content-Type": "application/json"
      }
      
      payload = {
          "prompt": "A cute cat playing in a garden on a sunny day, high quality",
          "model": "veo3",
          "aspectRatio": "16:9",
          "callBackUrl": "https://your-website.com/callback"  # Optional
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

  generate_video()
  ```

  ```curl cURL
  curl -X POST "https://api.kie.ai/api/v1/veo/generate" \
    -H "Authorization: Bearer YOUR_API_KEY" \
    -H "Content-Type: application/json" \
    -d '{
      "prompt": "A cute cat playing in a garden on a sunny day, high quality",
      "model": "veo3",
      "aspectRatio": "16:9",
      "callBackUrl": "https://your-website.com/callback"
    }'
  ```
</CodeGroup>

### Image-to-Video Example

<CodeGroup>
  ```javascript Node.js
  const response = await fetch('https://api.kie.ai/api/v1/veo/generate', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer YOUR_API_KEY',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      prompt: "Make the person in this image wave and smile, with background gently swaying",
      imageUrls: ["https://your-domain.com/image.jpg"],
      model: "veo3",
      aspectRatio: "16:9"
    })
  });
  ```

  ```python Python
  payload = {
      "prompt": "Make the person in this image wave and smile, with background gently swaying",
      "imageUrls": ["https://your-domain.com/image.jpg"],
      "model": "veo3",
      "aspectRatio": "16:9"
  }

  response = requests.post(url, json=payload, headers=headers)
  ```

  ```curl cURL
  curl -X POST "https://api.kie.ai/api/v1/veo/generate" \
    -H "Authorization: Bearer YOUR_API_KEY" \
    -H "Content-Type: application/json" \
    -d '{
      "prompt": "Make the person in this image wave and smile, with background gently swaying",
      "imageUrls": ["https://your-domain.com/image.jpg"],
      "model": "veo3",
      "aspectRatio": "16:9"
    }'
  ```
</CodeGroup>

## Step 4: Check Task Status

Video generation typically takes a few minutes. You can get results through polling or callbacks.

### Polling Method

<CodeGroup>
  ```javascript Node.js
  async function checkStatus(taskId) {
    try {
      const response = await fetch(`https://api.kie.ai/api/v1/veo/record-info?taskId=${taskId}`, {
        method: 'GET',
        headers: {
          'Authorization': 'Bearer YOUR_API_KEY'
        }
      });
      
      const result = await response.json();
      
      if (response.ok && result.code === 200) {
        const data = result.data;
        
        switch(data.successFlag) {
          case 0:
            console.log('Generating...');
            break;
          case 1:
            console.log('Generation successful!');
            console.log('Video URLs:', JSON.parse(data.resultUrls));
            return data;
          case 2:
          case 3:
            console.log('Generation failed:', result.msg);
            break;
        }
        
        return null;
      } else {
        console.error('Status check failed:', result.msg || 'Unknown error');
        return null;
      }
    } catch (error) {
      console.error('Status check failed:', error.message);
      return null;
    }
  }

  // Usage example
  async function waitForCompletion(taskId) {
    let result = null;
    while (!result) {
      result = await checkStatus(taskId);
      if (!result) {
        await new Promise(resolve => setTimeout(resolve, 30000)); // Wait 30 seconds
      }
    }
    return result;
  }
  ```

  ```python Python
  import time

  def check_status(task_id):
      url = f"https://api.kie.ai/api/v1/veo/record-info?taskId={task_id}"
      headers = {"Authorization": "Bearer YOUR_API_KEY"}
      
      try:
          response = requests.get(url, headers=headers)
          result = response.json()
          
          if response.ok and result.get('code') == 200:
              data = result['data']
              success_flag = data['successFlag']
              
              if success_flag == 0:
                  print("Generating...")
                  return None
              elif success_flag == 1:
                  print("Generation successful!")
                  video_urls = json.loads(data['resultUrls'])
                  print(f"Video URLs: {video_urls}")
                  return data
              else:
                  print(f"Generation failed: {result['msg']}")
                  return False
          else:
              print(f"Status check failed: {result.get('msg', 'Unknown error')}")
              return None
              
      except requests.exceptions.RequestException as e:
          print(f"Status check failed: {e}")
          return None

  def wait_for_completion(task_id):
      while True:
          result = check_status(task_id)
          if result is not None:
              return result
          time.sleep(30)  # Wait 30 seconds
  ```

  ```curl cURL
  curl -X GET "https://api.kie.ai/api/v1/veo/record-info?taskId=YOUR_TASK_ID" \
    -H "Authorization: Bearer YOUR_API_KEY"
  ```
</CodeGroup>

### Status Descriptions

| successFlag | Description                                                         |
| ----------- | ------------------------------------------------------------------- |
| 0           | Generating - Task is currently being processed                      |
| 1           | Success - Task completed successfully                               |
| 2           | Failed - Task generation failed                                     |
| 3           | Generation Failed - Task created successfully but generation failed |

## Step 5: Get HD Video (Optional)

If you use 16:9 aspect ratio to generate videos, you can get the 1080P high-definition version:

<CodeGroup>
  ```javascript Node.js
  async function get1080pVideo(taskId) {
    try {
      const response = await fetch(`https://api.kie.ai/api/v1/veo/get-1080p-video?taskId=${taskId}`, {
        method: 'GET',
        headers: {
          'Authorization': 'Bearer YOUR_API_KEY'
        }
      });
      
      const data = await response.json();
      
      if (response.ok && data.code === 200) {
        console.log('1080P video:', data);
        return data;
      } else {
        console.error('Failed to get 1080P video:', data.msg || 'Unknown error');
        return null;
      }
    } catch (error) {
      console.error('Failed to get 1080P video:', error.message);
      return null;
    }
  }
  ```

  ```python Python
  def get_1080p_video(task_id):
      url = f"https://api.kie.ai/api/v1/veo/get-1080p-video?taskId={task_id}"
      headers = {"Authorization": "Bearer YOUR_API_KEY"}
      
      try:
          response = requests.get(url, headers=headers)
          result = response.json()
          
          if response.ok and result.get('code') == 200:
              print(f"1080P video: {result}")
              return result
          else:
              print(f"Failed to get 1080P video: {result.get('msg', 'Unknown error')}")
              return None
      except requests.exceptions.RequestException as e:
          print(f"Failed to get 1080P video: {e}")
          return None
  ```

  ```curl cURL
  curl -X GET "https://api.kie.ai/api/v1/veo/get-1080p-video?taskId=YOUR_TASK_ID" \
    -H "Authorization: Bearer YOUR_API_KEY"
  ```
</CodeGroup>

<Info>
  **Note**: 1080P video requires additional processing time. It's recommended to wait a few minutes after the original video generation is completed before calling this endpoint.
</Info>

## Callback Handling (Recommended)

Compared to polling, callback mechanism is more efficient. Set the `callBackUrl` parameter, and the system will automatically push results when tasks complete:

<CodeGroup>
  ```javascript Node.js Express
  const express = require('express');
  const app = express();

  app.use(express.json());

  app.post('/veo3-callback', (req, res) => {
    const { code, msg, data } = req.body;
    
    console.log('Received callback:', {
      taskId: data.taskId,
      status: code,
      message: msg
    });
    
    if (code === 200) {
      // Video generation successful
      const videoUrls = JSON.parse(data.info.resultUrls);
      console.log('Video generation successful:', videoUrls);
      
      // Process the generated videos...
      downloadAndProcessVideos(videoUrls);
    } else {
      console.log('Video generation failed:', msg);
    }
    
    // Return 200 to confirm callback received
    res.status(200).json({ status: 'received' });
  });

  app.listen(3000, () => {
    console.log('Callback server running on port 3000');
  });
  ```

  ```python Python Flask
  from flask import Flask, request, jsonify

  app = Flask(__name__)

  @app.route('/veo3-callback', methods=['POST'])
  def handle_callback():
      data = request.json
      
      code = data.get('code')
      msg = data.get('msg')
      task_data = data.get('data', {})
      
      print(f"Received callback: {task_data.get('taskId')}, status: {code}")
      
      if code == 200:
          # Video generation successful
          video_urls = json.loads(task_data['info']['resultUrls'])
          print(f"Video generation successful: {video_urls}")
          
          # Process the generated videos...
          download_and_process_videos(video_urls)
      else:
          print(f"Video generation failed: {msg}")
      
      return jsonify({'status': 'received'}), 200

  if __name__ == '__main__':
      app.run(host='0.0.0.0', port=3000)
  ```
</CodeGroup>

## Complete Example: From Generation to Download

<CodeGroup>
  ```javascript Node.js Complete Workflow
  const fs = require('fs');
  const https = require('https');

  class Veo3Client {
    constructor(apiKey) {
      this.apiKey = apiKey;
      this.baseUrl = 'https://api.kie.ai';
      this.headers = {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json'
      };
    }

    // Generate video
    async generateVideo(prompt, options = {}) {
      const payload = {
        prompt,
        model: options.model || 'veo3',
        aspectRatio: options.aspectRatio || '16:9',
        ...options
      };

      try {
        const response = await fetch(`${this.baseUrl}/api/v1/veo/generate`, {
          method: 'POST',
          headers: this.headers,
          body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (response.ok && data.code === 200) {
          return data.data.taskId;
        } else {
          throw new Error(`Video generation failed: ${data.msg || 'Unknown error'}`);
        }
      } catch (error) {
        throw new Error(`Video generation failed: ${error.message}`);
      }
    }

    // Check status
    async getStatus(taskId) {
      try {
        const response = await fetch(`${this.baseUrl}/api/v1/veo/record-info?taskId=${taskId}`, {
          method: 'GET',
          headers: { 'Authorization': `Bearer ${this.apiKey}` }
        });
        
        const data = await response.json();
        
        if (response.ok && data.code === 200) {
          return data.data;
        } else {
          throw new Error(`Status check failed: ${data.msg || 'Unknown error'}`);
        }
      } catch (error) {
        throw new Error(`Status check failed: ${error.message}`);
      }
    }

    // Wait for completion
    async waitForCompletion(taskId, maxWaitTime = 600000) { // Default max wait 10 minutes
      const startTime = Date.now();
      
      while (Date.now() - startTime < maxWaitTime) {
        const status = await this.getStatus(taskId);
        
        console.log(`Task ${taskId} status: ${status.successFlag}`);
        
        if (status.successFlag === 1) {
          return JSON.parse(status.resultUrls);
        } else if (status.successFlag === 2 || status.successFlag === 3) {
          throw new Error('Video generation failed');
        }
        
        await new Promise(resolve => setTimeout(resolve, 30000)); // Wait 30 seconds
      }
      
      throw new Error('Task timeout');
    }

    // Download video
    async downloadVideo(url, filename) {
      return new Promise((resolve, reject) => {
        const file = fs.createWriteStream(filename);
        
        https.get(url, (response) => {
          if (response.statusCode === 200) {
            response.pipe(file);
            file.on('finish', () => {
              file.close();
              console.log(`Video downloaded: ${filename}`);
              resolve(filename);
            });
          } else {
            reject(new Error(`Download failed: HTTP ${response.statusCode}`));
          }
        }).on('error', reject);
      });
    }

    // Complete workflow
    async generateAndDownload(prompt, filename = 'video.mp4', options = {}) {
      try {
        console.log('Starting video generation...');
        const taskId = await this.generateVideo(prompt, options);
        console.log(`Task submitted: ${taskId}`);
        
        console.log('Waiting for completion...');
        const videoUrls = await this.waitForCompletion(taskId);
        console.log('Video generation completed!');
        
        console.log('Starting video download...');
        await this.downloadVideo(videoUrls[0], filename);
        
        return { taskId, videoUrls, filename };
      } catch (error) {
        console.error('Error:', error.message);
        throw error;
      }
    }
  }

  // Usage example
  async function main() {
    const client = new Veo3Client('YOUR_API_KEY');
    
    try {
      const result = await client.generateAndDownload(
        'A cute cat playing in a garden on a sunny day, high quality',
        'cute_cat.mp4',
        { aspectRatio: '16:9' }
      );
      
      console.log('Complete!', result);
    } catch (error) {
      console.error('Generation failed:', error.message);
    }
  }

  main();
  ```
</CodeGroup>

## Best Practices

<CardGroup cols={2}>
  <Card title="Optimize Prompts" icon="lightbulb">
    * Use detailed and specific descriptions
    * Include actions, scenes, and style information
    * Avoid vague or contradictory descriptions
  </Card>

  <Card title="Choose Models Wisely" icon="gear">
    * `veo3`: Quality model, higher quality
    * `veo3_fast`: Fast model, quicker generation
  </Card>

  <Card title="Handle Exceptions" icon="shield">
    * Implement retry mechanisms
    * Handle network and API errors
    * Log errors for debugging
  </Card>

  <Card title="Resource Management" icon="clock">
    * Download and save videos promptly
    * Control concurrent request numbers reasonably
    * Monitor API usage quotas
  </Card>
</CardGroup>

## Frequently Asked Questions

<AccordionGroup>
  <Accordion title="How long does generation take?">
    Typically 2-5 minutes, depending on video complexity and server load. Use `veo3_fast` model for faster generation speed.
  </Accordion>

  <Accordion title="What image formats are supported?">
    Supports common image formats including JPG, PNG, WebP, etc. Ensure image URLs are accessible to the API server.
  </Accordion>

  <Accordion title="How to get better video quality?">
    * Use detailed and specific prompts
    * Choose `veo3` standard model over fast model
    * For 16:9 videos, get 1080P high-definition version
  </Accordion>

  <Accordion title="Do video URLs have expiry dates?">
    Generated video URLs have certain validity periods. It's recommended to download and save them to your storage system promptly.
  </Accordion>

  <Accordion title="How to handle generation failures?">
    * Check if prompts violate content policies
    * Confirm image URLs are accessible
    * Review specific error messages
    * Contact technical support if necessary
  </Accordion>

  <Accordion title="How to generate a Veo 3 video longer than 8 seconds?">
    Clips made directly in VEO 3 are limited to 8 seconds. Anything longer has been edited externally after export.
  </Accordion>
</AccordionGroup>

## Next Steps

<CardGroup cols={3}>
  <Card title="API Reference" icon="book" href="/veo3-api/generate-veo-3-video">
    View complete API parameters and response formats
  </Card>

  <Card title="Callback Handling" icon="webhook" href="/veo3-api/generate-veo-3-video-callbacks">
    Learn how to handle task completion callbacks
  </Card>

  <Card title="Get Details" icon="video" href="/veo3-api/get-veo-3-video-details">
    Learn how to query task status and results
  </Card>
</CardGroup>

***

If you encounter any issues during usage, please contact our technical support: [support@kie.ai](mailto:support@kie.ai)
