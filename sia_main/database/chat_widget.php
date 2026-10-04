<style>

#aiChatBtn {
    position: fixed;
    right: 20px;
    bottom: 20px;
    width: 56px;
    height: 56px;
    border: 0;
    border-radius: 50%;
    background: #183d00;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(0, 0, 0, .25);
    z-index: 9998;
}

#aiChatBox {
    position: fixed;
    right: 20px;
    bottom: 88px;
    width: 340px;
    max-width: calc(100vw - 40px);
    height: 460px;
    max-height: calc(100vh - 120px);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, .25);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 9999;
    font-family: system-ui, sans-serif;
}

#aiChatBox.open {
    display: flex;
}

#aiChatHead {
    background: #183d00;
    color: #fff;
    padding: 12px 14px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#aiChatHead button {
    background: none;
    border: 0;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
}

#aiChatMsgs {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    background: #f4f6f2;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.aiMsg {
    max-width: 85%;
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.4;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.aiMsg.user {
    align-self: flex-end;
    background: #183d00;
    color: #fff;
}

.aiMsg.bot {
    align-self: flex-start;
    background: #fff;
    color: #1c2a24;
    border: 1px solid #e1e6dd;
}

#aiChatForm {
    display: flex;
    gap: 6px;
    padding: 10px;
    border-top: 1px solid #e1e6dd;
    background: #fff;
}

#aiChatInput {
    flex: 1;
    padding: 9px 12px;
    border: 1px solid #cfd6ca;
    border-radius: 20px;
    font-size: 14px;
    outline: none;
}

#aiChatForm button {
    border: 0;
    background: #183d00;
    color: #fff;
    border-radius: 20px;
    padding: 0 16px;
    cursor: pointer;
}

#aiChatForm button:disabled {
    opacity: .5;
    cursor: default;
}

</style>

<button id="aiChatBtn" type="button" aria-label="Open AI assistant">💬</button>

<div id="aiChatBox" role="dialog" aria-label="AI assistant">

        <div id="aiChatHead">
        <span>Farm Market Assistant</span>
        <button type="button" id="aiChatClose" aria-label="Close">×</button>
    </div>

        <div id="aiChatMsgs">
        <div class="aiMsg bot">Hi! Ask me about available products, prices, stock, or your orders.</div>
    </div>

        <form id="aiChatForm" autocomplete="off">
        <input id="aiChatInput" type="text" placeholder="Type your question..." maxlength="500">
        <button type="submit">Send</button>
    </form>

</div>

<script>
(function () {

    const box     = document.getElementById('aiChatBox');
    const msgs    = document.getElementById('aiChatMsgs');
    const form    = document.getElementById('aiChatForm');
    const input   = document.getElementById('aiChatInput');
    const sendBtn = form.querySelector('button');

    document.getElementById('aiChatBtn').onclick = () => {
        box.classList.toggle('open');
        input.focus();
    };

    document.getElementById('aiChatClose').onclick = () => {
        box.classList.remove('open');
    };

    function add(text, who) {
        const bubble = document.createElement('div');
        bubble.className = 'aiMsg ' + who;
        bubble.textContent = text;
        msgs.appendChild(bubble);
        msgs.scrollTop = msgs.scrollHeight;
        return bubble;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const question = input.value.trim();
        if (!question) return;

        add(question, 'user');
        input.value = '';
        sendBtn.disabled = true;

        const waiting = add('Thinking...', 'bot');

        try {

            const res = await fetch('../database/chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: question })
            });

            const data = await res.json();

            waiting.textContent = data.success
                ? data.answer
                : (data.message || 'Something went wrong.');

        } catch (err) {
            waiting.textContent = 'Could not reach the chatbot. Check that Apache is running.';
        }

        sendBtn.disabled = false;
        msgs.scrollTop = msgs.scrollHeight;
    });

})();
</script>