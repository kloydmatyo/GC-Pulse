import { websiteContext } from '/gcpulseeee/chatbot/chatBotKnowledge.js';

// API setup
const API_KEY = "AIzaSyC7UP1fRpe1E68INexjpP6zOZUY0icDKYs";
const API_URL = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${API_KEY}`;

// Add this new function to fetch from database
async function fetchDatabaseContext() {
    try {
        const response = await fetch('/gcpulseeee/chatbot/fetch_db_context.php');
        if (!response.ok) {
            throw new Error('Failed to fetch database context');
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching database context:', error);
        return null;
    }
}

export async function generateText(prompt) {
    try {
        // Fetch dynamic content from database
        const dbContext = await fetchDatabaseContext();
        
        // Combine static and dynamic context
        const fullContext = `
            ${websiteContext}
            
            Current Database Information:
            ${dbContext ? JSON.stringify(dbContext, null, 2) : ''}
            
            User question: ${prompt}
            
            Your helpful response:`;

        const requestOptions = {
            method: "POST",
            headers: { 
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                contents: [{
                    parts: [{ text: fullContext }]
                }],
                generationConfig: {
                    temperature: 1,
                    topP: 0.95,
                    topK: 40,
                    maxOutputTokens: 8192
                }
            })
        };

        const response = await fetch(API_URL, requestOptions);
        
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error?.message || `HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        if (!data?.candidates?.[0]?.content?.parts?.[0]?.text) {
            throw new Error("Invalid response format from API");
        }

        return data.candidates[0].content.parts[0].text;
        
    } catch (error) {
        console.error("ChatBot API Error:", error.message);
        throw new Error("Failed to generate response. Please try again later.");
    }
}