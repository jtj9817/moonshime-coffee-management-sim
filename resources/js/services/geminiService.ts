import { GoogleGenAI } from '@google/genai';

import { InventoryRecord, Item, Location } from '../types';

// Initialize the API client
const apiKey = process.env.API_KEY || '';
const ai = new GoogleGenAI({ apiKey });

export interface AdvisoryResponse {
    analysis: string;
    recommendations: string[];
}

export const getInventoryAdvice = async (
    inventory: InventoryRecord[],
    items: Item[],
    locations: Location[],
): Promise<AdvisoryResponse> => {
    if (!apiKey) {
        return {
            analysis: 'API Key missing. Running in simulation mode.',
            recommendations: [
                'Check Uptown Kiosk milk levels.',
                'Consider bulk ordering cups for HQ to save costs.',
                'Monitor seasonal trends for syrup consumption.',
            ],
        };
    }

    try {
        const prompt = `
      You are an expert supply chain manager for a coffee shop chain called "Moonshine".
      Analyze the following current inventory state and provide strategic advice.
      Focus on risks (stockouts, expiration) and opportunities (bulk savings vs storage costs).

      Data:
      Locations: ${JSON.stringify(locations.map((l) => ({ name: l.name, id: l.id })))}
      Items: ${JSON.stringify(items.map((i) => ({ name: i.name, id: i.id, unit: i.unit, perishable: i.isPerishable })))}
      Current Inventory: ${JSON.stringify(inventory)}

      Return a JSON object with:
      1. 'analysis': A brief paragraph analyzing the current health of the supply chain.
      2. 'recommendations': An array of 3 actionable short bullet points.
    `;

        const response = await ai.models.generateContent({
            model: 'gemini-3-flash-preview',
            contents: prompt,
            config: {
                responseMimeType: 'application/json',
            },
        });

        let text = response.text || '{}';

        // Cleanup markdown code fences if present
        if (text.startsWith('```json')) {
            text = text.replace(/^```json\s*/, '').replace(/\s*```$/, '');
        } else if (text.startsWith('```')) {
            text = text.replace(/^```\s*/, '').replace(/\s*```$/, '');
        }

        const json = JSON.parse(text);

        return {
            analysis: json.analysis || 'Analysis currently unavailable.',
            recommendations: json.recommendations || [
                'Check stock levels manually.',
            ],
        };
    } catch (error) {
        console.error('Gemini API Error:', error);
        return {
            analysis: 'Unable to connect to AI advisor at this moment.',
            recommendations: [
                'Ensure network connection is stable.',
                'Check API quotas.',
            ],
        };
    }
};
