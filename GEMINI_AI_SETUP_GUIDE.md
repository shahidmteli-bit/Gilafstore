# Google Gemini AI Integration Guide

## 🤖 Overview
Integrate Google Gemini AI with your Gilaf chatbot for intelligent, context-aware responses that go beyond keyword matching.

---

## 🎯 Benefits of AI Integration

### **Before (Knowledge Base Only)**
- ❌ Limited to predefined keywords
- ❌ Can't understand context or variations
- ❌ Rigid responses
- ❌ Can't handle complex queries

### **After (Gemini AI)**
- ✅ Natural language understanding
- ✅ Context-aware conversations
- ✅ Handles complex questions
- ✅ Learns from conversation history
- ✅ More human-like responses
- ✅ Automatic fallback to knowledge base

---

## 📋 Setup Steps

### **Step 1: Get Gemini API Key**

1. Go to **Google AI Studio**: https://makersuite.google.com/app/apikey
2. Sign in with your Google account
3. Click **"Get API Key"** or **"Create API Key"**
4. Copy your API key (starts with `AIza...`)

**Important:** Keep this key secure! Never share it publicly.

---

### **Step 2: Configure API Key**

Edit `chatbot_config.php`:

```php
define('GEMINI_API_KEY', 'YOUR_ACTUAL_API_KEY_HERE');
```

Replace `YOUR_ACTUAL_API_KEY_HERE` with your real API key from Step 1.

**Example:**
```php
define('GEMINI_API_KEY', 'AIzaSyDXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
```

---

### **Step 3: Enable AI in Chatbot**

The AI is already enabled by default. To toggle:

Edit `assets/js/chatbot.js` line 7:
```javascript
this.useAI = true;  // true = use AI, false = use only knowledge base
```

---

### **Step 4: Test the Integration**

1. **Refresh your website** (Ctrl + F5)
2. **Open chatbot**
3. **Try complex questions:**
   - "What's the difference between your saffron grades?"
   - "I ordered 3 days ago but haven't received tracking info"
   - "Can I return opened saffron if I don't like the quality?"
   - "Do you ship to Canada and how long does it take?"

The AI should provide contextual, intelligent responses!

---

## ⚙️ How It Works

### **Flow Diagram**
```
User Message
    ↓
Try Gemini AI
    ↓
Success? → Send AI Response
    ↓
Failed? → Fallback to Knowledge Base
    ↓
No Match? → Escalate to Human Support
```

### **Features**

1. **Context Awareness**
   - Remembers last 3 message exchanges
   - Understands follow-up questions
   - Maintains conversation flow

2. **Smart Escalation**
   - Detects when human help is needed
   - Shows contact options automatically
   - Never leaves customer stuck

3. **Quick Actions**
   - AI generates relevant action buttons
   - Based on response content
   - Helps users navigate quickly

4. **Conversation Logging**
   - Stores all interactions in database
   - Tracks AI vs knowledge base usage
   - Helps improve responses over time

---

## 🎨 Customization

### **Adjust AI Personality**

Edit `chatbot_gemini.php` line 36-68 (system context):

```php
$system_context = "You are a helpful customer support AI assistant for Gilaf...

Your role:
- Be polite, professional, and concise
- Use emojis sparingly for a friendly tone
- Keep responses under 200 words
...
```

**Customize:**
- Tone (formal, casual, friendly)
- Response length
- Emoji usage
- Specific instructions

### **Adjust AI Temperature**

Edit `chatbot_config.php`:

```php
define('GEMINI_TEMPERATURE', 0.7);  // 0.0 = precise, 1.0 = creative
```

- **0.0-0.3**: Very precise, factual
- **0.4-0.7**: Balanced (recommended)
- **0.8-1.0**: Creative, varied responses

### **Change Max Response Length**

Edit `chatbot_config.php`:

```php
define('GEMINI_MAX_TOKENS', 500);  // Increase for longer responses
```

### **Disable AI (Use Knowledge Base Only)**

Edit `chatbot_config.php`:

```php
define('CHATBOT_USE_AI', false);
```

Or edit `assets/js/chatbot.js` line 7:

```javascript
this.useAI = false;
```

---

## 📊 Monitoring & Analytics

### **View AI Usage**

Access database table: `chatbot_analytics`

```sql
SELECT 
    COUNT(*) as total_conversations,
    SUM(CASE WHEN matched_category = 'gemini_ai' THEN 1 ELSE 0 END) as ai_responses,
    SUM(escalated) as escalations
FROM chatbot_analytics;
```

### **Check Popular Queries**

```sql
SELECT user_message, COUNT(*) as frequency
FROM chatbot_analytics
GROUP BY user_message
ORDER BY frequency DESC
LIMIT 10;
```

---

## 🔒 Security Best Practices

1. **Protect API Key**
   - Never commit `chatbot_config.php` to Git
   - Add to `.gitignore`
   - Use environment variables in production

2. **Rate Limiting**
   - Enable in `chatbot_config.php`
   - Prevents API abuse
   - Protects your quota

3. **Input Validation**
   - Already implemented in `chatbot_gemini.php`
   - Sanitizes user input
   - Prevents injection attacks

---

## 💰 API Costs & Limits

### **Gemini API Pricing (as of 2024)**

**Free Tier:**
- 60 requests per minute
- 1,500 requests per day
- Perfect for small to medium sites

**Paid Tier:**
- Higher rate limits
- More requests per day
- See: https://ai.google.dev/pricing

### **Optimize Costs**

1. **Cache common responses**
2. **Use knowledge base for simple queries**
3. **Limit conversation history** (already set to 3 exchanges)
4. **Set reasonable max tokens** (500 is good)

---

## 🐛 Troubleshooting

### **AI Not Responding**

**Check:**
1. API key is correct in `chatbot_config.php`
2. Browser console for errors (F12)
3. PHP error logs
4. API quota not exceeded

**Test API Key:**
```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=YOUR_API_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"contents":[{"parts":[{"text":"Hello"}]}]}'
```

### **Slow Responses**

**Solutions:**
1. Reduce `GEMINI_MAX_TOKENS`
2. Decrease `GEMINI_TIMEOUT`
3. Limit conversation history
4. Check internet connection

### **Fallback to Knowledge Base**

**This is normal!** AI falls back when:
- API key invalid
- Network error
- API quota exceeded
- Response blocked by safety filters

### **Error: "API key not configured"**

**Fix:**
1. Ensure `chatbot_config.php` exists
2. Check API key is set correctly
3. Verify file permissions
4. Clear PHP opcache

---

## 🎯 Best Practices

### **1. Train with Context**
Update system context with:
- Your actual policies
- Real product information
- Common customer issues
- Your brand voice

### **2. Monitor Performance**
- Check `chatbot_analytics` weekly
- Identify common questions
- Update knowledge base
- Refine AI instructions

### **3. Balance AI & Knowledge Base**
- Use AI for complex queries
- Use knowledge base for simple FAQs
- Faster responses for common questions
- Lower API costs

### **4. Test Regularly**
- Try edge cases
- Test escalation flow
- Verify contact information
- Check mobile experience

---

## 📈 Advanced Features (Future)

Potential enhancements:
- [ ] Multi-language support
- [ ] Sentiment analysis
- [ ] Product recommendations
- [ ] Image understanding (Gemini Pro Vision)
- [ ] Voice input/output
- [ ] Integration with CRM
- [ ] A/B testing responses
- [ ] Custom training on your data

---

## 🔄 Switching Between AI & Knowledge Base

### **Use AI When:**
- Complex questions
- Context needed
- Variations in phrasing
- Follow-up questions
- Natural conversations

### **Use Knowledge Base When:**
- Simple FAQs
- Exact keyword matches
- Faster responses needed
- Offline mode
- API quota concerns

### **Toggle Easily:**

**Globally** - `chatbot_config.php`:
```php
define('CHATBOT_USE_AI', true/false);
```

**Per Instance** - `chatbot.js`:
```javascript
this.useAI = true/false;
```

---

## 📞 Support

**Issues with AI Integration?**
- Check API key validity
- Review PHP error logs
- Test with simple queries first
- Verify database tables exist

**Need Help?**
- Google AI Studio: https://ai.google.dev/
- Gemini API Docs: https://ai.google.dev/docs
- Support: support@gilaf.com

---

## ✅ Quick Checklist

Before going live with AI:
- [ ] Gemini API key obtained
- [ ] API key configured in `chatbot_config.php`
- [ ] System context customized
- [ ] Tested with various queries
- [ ] Fallback to knowledge base works
- [ ] Escalation flow tested
- [ ] Contact information verified
- [ ] Database logging enabled
- [ ] API quota monitored
- [ ] Security best practices followed

---

## 📝 Configuration Summary

**Files Modified:**
- ✅ `chatbot_gemini.php` - AI backend handler
- ✅ `chatbot_config.php` - API key & settings
- ✅ `assets/js/chatbot.js` - Frontend AI integration

**Database:**
- ✅ `chatbot_analytics` - Conversation logging

**Key Settings:**
- API Key: In `chatbot_config.php`
- AI Toggle: `this.useAI` in `chatbot.js`
- Temperature: 0.7 (balanced)
- Max Tokens: 500 (concise responses)
- Timeout: 30 seconds

---

**Version**: 1.0  
**Last Updated**: January 2026  
**Powered by**: Google Gemini AI  
**Developed for**: Gilaf Foods & Spices
