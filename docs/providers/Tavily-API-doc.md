Introduction
Easily integrate our APIs with your services.

Looking for the Python or JavaScript SDK Reference? Head to our SDKs page to see how to natively integrate Tavily in your project.
​
Base URL
The base URL for all requests to the Tavily API is:
https://api.tavily.com
​
Authentication
All Tavily endpoints are authenticated using API keys. Get your free API key.


curl -X POST https://api.tavily.com/search \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer tvly-YOUR_API_KEY" \
  -d '{"query": "Who is Leo Messi?"}'


## Search

```
curl --request POST \
  --url https://api.tavily.com/search \
  --header 'Authorization: Bearer <token>' \
  --header 'Content-Type: application/json' \
  --data '{
  "query": "who is Leo Messi?",
  "auto_parameters": false,
  "topic": "general",
  "search_depth": "basic",
  "chunks_per_source": 3,
  "max_results": 1,
  "time_range": null,
  "days": 7,
  "start_date": "2025-02-09",
  "end_date": "2000-01-28",
  "include_answer": true,
  "include_raw_content": true,
  "include_images": false,
  "include_image_descriptions": false,
  "include_favicon": false,
  "include_domains": [],
  "exclude_domains": [],
  "country": null
}'
```

### Search Response

```
{
  "query": "Who is Leo Messi?",
  "answer": "Lionel Messi, born in 1987, is an Argentine footballer widely regarded as one of the greatest players of his generation. He spent the majority of his career playing for FC Barcelona, where he won numerous domestic league titles and UEFA Champions League titles. Messi is known for his exceptional dribbling skills, vision, and goal-scoring ability. He has won multiple FIFA Ballon d'Or awards, numerous La Liga titles with Barcelona, and holds the record for most goals scored in a calendar year. In 2014, he led Argentina to the World Cup final, and in 2015, he helped Barcelona capture another treble. Despite turning 36 in June, Messi remains highly influential in the sport.",
  "images": [],
  "results": [
    {
      "title": "Lionel Messi Facts | Britannica",
      "url": "https://www.britannica.com/facts/Lionel-Messi",
      "content": "Lionel Messi, an Argentine footballer, is widely regarded as one of the greatest football players of his generation. Born in 1987, Messi spent the majority of his career playing for Barcelona, where he won numerous domestic league titles and UEFA Champions League titles. Messi is known for his exceptional dribbling skills, vision, and goal",
      "score": 0.81025416,
      "raw_content": null,
      "favicon": "https://britannica.com/favicon.png"
    }
  ],
  "auto_parameters": {
    "topic": "general",
    "search_depth": "basic"
  },
  "response_time": "1.67",
  "request_id": "123e4567-e89b-12d3-a456-426614174111"
}
```


## Tavily Extract

```
curl --request POST \
  --url https://api.tavily.com/extract \
  --header 'Authorization: Bearer <token>' \
  --header 'Content-Type: application/json' \
  --data '{
  "urls": "https://en.wikipedia.org/wiki/Artificial_intelligence",
  "include_images": false,
  "include_favicon": false,
  "extract_depth": "basic",
  "format": "markdown",
  "timeout": "None"
}'
```

### Tavily Extract Response 

```
{
  "results": [
    {
      "url": "https://en.wikipedia.org/wiki/Artificial_intelligence",
      "raw_content": "\"Jump to content\\nMain menu\\nSearch\\nAppearance\\nDonate\\nCreate account\\nLog in\\nPersonal tools\\n        Photograph your local culture, help Wikipedia and win!\\nToggle the table of contents\\nArtificial intelligence\\n161 languages\\nArticle\\nTalk\\nRead\\nView source\\nView history\\nTools\\nFrom Wikipedia, the free encyclopedia\\n\\\"AI\\\" redirects here. For other uses, see AI (disambiguation) and Artificial intelligence (disambiguation).\\nPart of a series on\\nArtificial intelligence (AI)\\nshow\\nMajor goals\\nshow\\nApproaches\\nshow\\nApplications\\nshow\\nPhilosophy\\nshow\\nHistory\\nshow\\nGlossary\\nvte\\nArtificial intelligence (AI), in its broadest sense, is intelligence exhibited by machines, particularly computer systems. It is a field of research in computer science that develops and studies methods and software that enable machines to perceive their environment and use learning and intelligence to take actions that maximize their chances of achieving defined goals.[1] Such machines may be called AIs.\\nHigh-profile applications of AI include advanced web search engines (e.g., Google Search); recommendation systems (used by YouTube, Amazon, and Netflix); virtual assistants (e.g., Google Assistant, Siri, and Alexa); autonomous vehicles (e.g., Waymo); generative and creative tools (e.g., ChatGPT and AI art); and superhuman play and analysis in strategy games (e.g., chess and Go)...................",
      "images": [],
      "favicon": "https://en.wikipedia.org/static/favicon/wikipedia.ico"
    }
  ],
  "failed_results": [],
  "response_time": 0.02,
  "request_id": "123e4567-e89b-12d3-a456-426614174111"
}
``` 