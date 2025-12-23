import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';

const settings = window.AIGatewaySettings || { restUrl: '', nonce: '', agents: [] };

if (settings.nonce) {
    apiFetch.use(apiFetch.createNonceMiddleware(settings.nonce));
}

const normalizeSchema = (schema) =>
    (schema || [])
        .map((field) => {
            if (typeof field === 'string') {
                return { key: field, label: field, type: 'text' };
            }
            if (!field || !field.key) {
                return null;
            }
            return {
                key: field.key,
                label: field.label || field.key,
                type: field.type || 'text',
                options: field.options || [],
                placeholder: field.placeholder || '',
                required: Boolean(field.required),
                env: field.env || '',
            };
        })
        .filter(Boolean);

function StudioApp() {
    const [agents, setAgents] = useState(settings.agents || []);
    const [agentId, setAgentId] = useState(settings.studioDefaultAgent || settings.agents?.[0]?.id || 0);
    const [instruction, setInstruction] = useState('');
    const [inputs, setInputs] = useState({});
    const [messages, setMessages] = useState([]);
    const [projects, setProjects] = useState([]);
    const [projectId, setProjectId] = useState(0);
    const [conversations, setConversations] = useState([]);
    const [conversationId, setConversationId] = useState(0);
    const [isBusy, setIsBusy] = useState(false);
    const [error, setError] = useState('');

    const saveMessage = async (conversation, role, content) => {
        if (!conversation) {
            return;
        }
        try {
            await apiFetch({
                path: `/ai/v1/conversations/${conversation}/messages`,
                method: 'POST',
                data: { role, content },
            });
        } catch (err) {
            setError(err.message || 'Failed to save message.');
        }
    };

    const agent = useMemo(
        () => agents.find((item) => item.id === agentId),
        [agents, agentId]
    );
    const schema = normalizeSchema(agent?.input_schema || []).filter((field) => !field.env);

    const resetInputs = () => {
        const defaults = {};
        schema.forEach((field) => {
            defaults[field.key] = '';
        });
        setInputs(defaults);
    };

    useEffect(() => {
        if (agents.length) {
            return;
        }
        let mounted = true;
        const loadAgents = async () => {
            try {
                const data = await apiFetch({
                    path: '/ai/v1/agents?enabled=1',
                    method: 'GET',
                });
                if (!mounted) {
                    return;
                }
                const list = Array.isArray(data) ? data : [];
                setAgents(list);
                const defaultId = settings.studioDefaultAgent || 0;
                const selected = list.find((item) => item.id === defaultId);
                setAgentId(selected?.id || list[0]?.id || 0);
            } catch (err) {
                if (mounted) {
                    setError(err.message || 'Failed to load agents.');
                }
            }
        };

        loadAgents();
        return () => {
            mounted = false;
        };
    }, [agents.length]);

    useEffect(() => {
        resetInputs();
    }, [agentId]);

    useEffect(() => {
        let mounted = true;
        const loadProjects = async () => {
            try {
                const data = await apiFetch({ path: '/ai/v1/projects', method: 'GET' });
                const list = Array.isArray(data) ? data : [];
                if (!mounted) {
                    return;
                }
                if (!list.length) {
                    const created = await apiFetch({
                        path: '/ai/v1/projects',
                        method: 'POST',
                        data: { name: 'General' },
                    });
                    setProjects([created]);
                    setProjectId(created.id);
                    return;
                }
                setProjects(list);
                setProjectId(list[0]?.id || 0);
            } catch (err) {
                if (mounted) {
                    setError(err.message || 'Failed to load projects.');
                }
            }
        };

        loadProjects();
        return () => {
            mounted = false;
        };
    }, []);

    useEffect(() => {
        if (!projectId) {
            return;
        }
        let mounted = true;
        const loadConversations = async () => {
            try {
                const data = await apiFetch({
                    path: `/ai/v1/conversations?project_id=${projectId}`,
                    method: 'GET',
                });
                if (!mounted) {
                    return;
                }
                const list = Array.isArray(data) ? data : [];
                setConversations(list);
                setConversationId(list[0]?.id || 0);
            } catch (err) {
                if (mounted) {
                    setError(err.message || 'Failed to load conversations.');
                }
            }
        };

        loadConversations();
        return () => {
            mounted = false;
        };
    }, [projectId]);

    useEffect(() => {
        if (!conversationId) {
            setMessages([]);
            return;
        }
        let mounted = true;
        const loadConversation = async () => {
            try {
                const data = await apiFetch({
                    path: `/ai/v1/conversations/${conversationId}`,
                    method: 'GET',
                });
                if (mounted && data?.messages) {
                    setMessages(data.messages);
                }
            } catch (err) {
                if (mounted) {
                    setError(err.message || 'Failed to load conversation.');
                }
            }
        };

        loadConversation();
        return () => {
            mounted = false;
        };
    }, [conversationId]);

    const updateAssistant = (delta, done) => {
        setMessages((prev) => {
            if (!prev.length) {
                return prev;
            }
            const next = [...prev];
            const last = next[next.length - 1];
            if (last.role !== 'assistant') {
                return prev;
            }
            next[next.length - 1] = {
                ...last,
                content: done ? delta : last.content + delta,
            };
            return next;
        });
    };

    const runAgent = async () => {
        if (!agentId) {
            setError('No agent selected.');
            return;
        }

        setIsBusy(true);
        setError('');

        let activeConversationId = conversationId;
        if (!activeConversationId) {
            const created = await apiFetch({
                path: '/ai/v1/conversations',
                method: 'POST',
                data: { name: 'Conversation', project_id: projectId },
            });
            activeConversationId = created.id;
            setConversations((prev) => [created, ...prev]);
            setConversationId(created.id);
        }

        const userMessage = { role: 'user', content: instruction };
        setMessages((prev) => [...prev, userMessage, { role: 'assistant', content: '' }]);
        saveMessage(activeConversationId, 'user', instruction);

        try {
            const response = await fetch('/wp-json/ai/v1/run/stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce || '',
                },
                body: JSON.stringify({
                    agent_id: agentId,
                    instruction,
                    inputs,
                }),
            });

            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !response.body || !contentType.includes('text/event-stream')) {
                throw new Error('Stream unavailable');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const parts = buffer.split('\n\n');
                buffer = parts.pop() || '';

                parts.forEach((part) => {
                    const lines = part.split('\n');
                    lines.forEach((line) => {
                        if (!line.startsWith('data: ')) {
                            return;
                        }
                        const payload = line.slice(6).trim();
                        if (!payload) {
                            return;
                        }
                        try {
                            const data = JSON.parse(payload);
                            if (data.error) {
                                setError(data.error);
                                return;
                            }
                            if (data.delta) {
                                updateAssistant(data.delta, false);
                            }
                            if (data.done && data.full) {
                                updateAssistant(data.full, true);
                                saveMessage(activeConversationId, 'assistant', data.full);
                            }
                        } catch (err) {
                            setError(err.message || 'Stream parse error.');
                        }
                    });
                });
            }
        } catch (err) {
            setError(err.message || 'Stream failed.');
        } finally {
            setIsBusy(false);
            setInstruction('');
        }
    };

    return (
        <div className="ai-studio-app">
            <aside className="ai-studio-sidebar">
                <div className="ai-studio-sidebar-section">
                    <div className="ai-studio-sidebar-title">{__('Projects', 'ai-gateway')}</div>
                    <select
                        className="ai-studio-select"
                        value={projectId}
                        onChange={(event) => setProjectId(Number(event.target.value))}
                    >
                        {projects.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="ai-studio-sidebar-section">
                    <div className="ai-studio-sidebar-title">{__('Conversations', 'ai-gateway')}</div>
                    <div className="ai-studio-conversation-list">
                        {conversations.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                className={`ai-studio-conversation ${item.id === conversationId ? 'active' : ''}`}
                                onClick={() => setConversationId(item.id)}
                            >
                                <div className="ai-studio-conversation-title">{item.name}</div>
                                {item.last && <div className="ai-studio-conversation-last">{item.last}</div>}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        className="ai-studio-button secondary"
                        onClick={async () => {
                            const created = await apiFetch({
                                path: '/ai/v1/conversations',
                                method: 'POST',
                                data: { name: 'Conversation', project_id: projectId },
                            });
                            setConversations((prev) => [created, ...prev]);
                            setConversationId(created.id);
                        }}
                    >
                        {__('New chat', 'ai-gateway')}
                    </button>
                </div>
                <div className="ai-studio-sidebar-section">
                    <div className="ai-studio-sidebar-title">{__('Agents', 'ai-gateway')}</div>
                    <select
                        className="ai-studio-select"
                        value={agentId}
                        onChange={(event) => setAgentId(Number(event.target.value))}
                    >
                        {agents.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="ai-studio-sidebar-section">
                    <div className="ai-studio-sidebar-title">{__('Tools', 'ai-gateway')}</div>
                    <div className="ai-studio-sidebar-empty">{__('Assigned via agent.', 'ai-gateway')}</div>
                </div>
                <div className="ai-studio-sidebar-section">
                    <div className="ai-studio-sidebar-title">{__('Settings', 'ai-gateway')}</div>
                    <div className="ai-studio-sidebar-empty">{__('Configure in plugin settings.', 'ai-gateway')}</div>
                </div>
            </aside>

            <main className="ai-studio-main">
                <div className="ai-studio-topbar">
                    <div className="ai-studio-topbar-left">
                        <span className="ai-studio-topbar-label">{__('Active agent', 'ai-gateway')}</span>
                        <span className="ai-studio-topbar-value">{agent?.name || __('None', 'ai-gateway')}</span>
                    </div>
                    <div className="ai-studio-topbar-right">
                        <span className="ai-studio-status-dot" />
                        <span>{__('Ollama: online', 'ai-gateway')}</span>
                    </div>
                </div>
                <div className="ai-studio-chat">
                    {messages.map((msg, index) => (
                        <div key={index} className={`ai-studio-message ai-studio-${msg.role}`}>
                            <div className="ai-studio-message-role">{msg.role}</div>
                            <div className="ai-studio-message-content">{msg.content}</div>
                        </div>
                    ))}
                </div>

                {schema.length > 0 && (
                    <div className="ai-studio-inputs">
                        {schema.map((field) => (
                            <label key={field.key} className="ai-studio-field">
                                <span>{field.label}</span>
                                <input
                                    type={field.type === 'number' ? 'number' : field.type === 'url' ? 'url' : 'text'}
                                    value={inputs[field.key] || ''}
                                    placeholder={field.placeholder}
                                    onChange={(event) =>
                                        setInputs((prev) => ({ ...prev, [field.key]: event.target.value }))
                                    }
                                />
                            </label>
                        ))}
                    </div>
                )}

                <div className="ai-studio-composer">
                    {error && <div className="ai-studio-error">{error}</div>}
                    <div className="ai-studio-composer-row">
                        <button className="ai-studio-button icon" type="button" title={__('Add', 'ai-gateway')}>
                            +
                        </button>
                        <textarea
                            className="ai-studio-textarea"
                            rows="3"
                            placeholder={__('Ask something...', 'ai-gateway')}
                            value={instruction}
                            onChange={(event) => setInstruction(event.target.value)}
                        />
                        <button className="ai-studio-button" onClick={runAgent} disabled={isBusy}>
                            {isBusy ? __('Running...', 'ai-gateway') : __('Send', 'ai-gateway')}
                        </button>
                    </div>
                    <div className="ai-studio-actions">
                        <button className="ai-studio-button secondary" onClick={resetInputs}>
                            {__('Reset', 'ai-gateway')}
                        </button>
                    </div>
                </div>
            </main>
        </div>
    );
}

registerBlockType('ai-gateway/studio', {
    title: __('AI Studio', 'ai-gateway'),
    icon: 'format-chat',
    category: 'widgets',
    edit() {
        const props = useBlockProps({
            className: 'ai-studio-block-placeholder',
        });
        return <div {...props}>{__('AI Studio App', 'ai-gateway')}</div>;
    },
    save() {
        const props = useBlockProps.save({
            className: 'ai-studio-app',
        });
        return <div {...props} />;
    },
});

const mountApp = () => {
    const nodes = document.querySelectorAll('.ai-studio-app');
    if (!nodes.length) {
        return;
    }
    nodes.forEach((node) => {
        window.wp.element.render(<StudioApp />, node);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountApp);
} else {
    mountApp();
}
