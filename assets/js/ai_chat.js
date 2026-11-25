/**
 * Module Chatbot Trợ Lý Tài Chính AI Tương Tác
 * Phụ trách: Trần Gia Huy (Frontend)
 */
(function () {
    'use strict';

    // TODO (Trần Gia Huy):
    // 1. Tạo ô input nhập câu hỏi và nút gửi trên Dashboard (dưới phần Trợ lý AI).
    // 2. Gợi ý 3 câu hỏi mẫu (Quick prompts) một chạm:
    //    - "Làm sao để tháng sau tiết kiệm được 2 triệu?"
    //    - "Tôi có nên mua điện thoại mới lúc này không?"
    //    - "Cách cắt giảm 30% tiền ăn uống?"
    // 3. Gửi câu hỏi đến /api/ai/chat.php qua POST { question: "..." }.
    // 4. Hiển thị tin nhắn trả lời của AI kèm hiệu ứng loading.
    
    window.AIChatbot = {
        sendQuestion: async function (question) {
            console.log('Sending question to AI:', question);
            // return await apiFetch('/ai/chat.php', { method: 'POST', body: JSON.stringify({ question }) });
        }
    };
})();
