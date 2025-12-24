import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';

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

const WORKFLOW_NODE_WIDTH = 240;
const WORKFLOW_NODE_HEIGHT = 140;

const createNodeId = () => `node_${Date.now()}_${Math.floor(Math.random() * 1000)}`;

const normalizeWorkflowNodes = (nodes) =>
    (Array.isArray(nodes) ? nodes : []).map((node, index) => {
        const fallbackX = 40 + index * 40;
        const fallbackY = 40 + index * 100;
        return {
            id: node.id || createNodeId(),
            type: node.type || 'run_agent',
            x: Number.isFinite(node.x) ? node.x : fallbackX,
            y: Number.isFinite(node.y) ? node.y : fallbackY,
            ...node,
        };
    });

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
    const [showSettingsModal, setShowSettingsModal] = useState(false);
    const [workspaceMode, setWorkspaceMode] = useState(
        window.localStorage.getItem('ai_gateway_workspace_mode') || 'chat'
    );
    const [activeConversationActions, setActiveConversationActions] = useState(null);
    const [moveProjectId, setMoveProjectId] = useState(0);
    const [moveProjectName, setMoveProjectName] = useState('');
    const [showPlusMenu, setShowPlusMenu] = useState(false);
    const [workspaceWidth, setWorkspaceWidth] = useState(
        Number(window.localStorage.getItem('ai_gateway_workspace_width')) || 360
    );
    const [isDragging, setIsDragging] = useState(false);
    const [selectedTools, setSelectedTools] = useState([]);
    const [uploadUrl, setUploadUrl] = useState('');
    const [uploadStatus, setUploadStatus] = useState('');
    const [selectedMedia, setSelectedMedia] = useState(null);
    const [chainAgents, setChainAgents] = useState([]);
    const [useChain, setUseChain] = useState(false);
    const [showArchived, setShowArchived] = useState(false);
    const [isTyping, setIsTyping] = useState(false);
    const [activeProjectActions, setActiveProjectActions] = useState(false);
    const [projectName, setProjectName] = useState('');
    const [editorType, setEditorType] = useState('article');
    const [editorTitle, setEditorTitle] = useState('');
    const [editorContent, setEditorContent] = useState('');
    const [editorStatus, setEditorStatus] = useState('');
    const [injectMode, setInjectMode] = useState('append');
    const [createType, setCreateType] = useState('article');
    const [chatToolStatus, setChatToolStatus] = useState('');
    const [errorLogs, setErrorLogs] = useState([]);
    const [draftId, setDraftId] = useState(0);
    const [previewUrl, setPreviewUrl] = useState('');
    const [workflowNodes, setWorkflowNodes] = useState([]);
    const [workflowStatus, setWorkflowStatus] = useState('');
    const [workflowVersions, setWorkflowVersions] = useState([]);
    const [workflowNote, setWorkflowNote] = useState('');
    const [nodeDrag, setNodeDrag] = useState(null);
    const workflowNodesRef = useRef([]);

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
            logError(err.message || 'Failed to save message.');
        }
    };

    const logError = (message) => {
        setError(message);
        setErrorLogs((prev) => [
            { message, time: new Date().toISOString() },
            ...prev.slice(0, 9),
        ]);
    };

    const agent = useMemo(
        () => agents.find((item) => item.id === agentId),
        [agents, agentId]
    );
    const schema = normalizeSchema(agent?.input_schema || []).filter((field) => !field.env);
    const adminBase = settings.adminUrl || '/wp-admin/';
    const agentTools = agent?.tools || [];
    const agentById = useMemo(() => {
        const map = {};
        agents.forEach((item) => {
            map[item.id] = item;
        });
        return map;
    }, [agents]);

    const resetInputs = () => {
        const defaults = {};
        schema.forEach((field) => {
            defaults[field.key] = '';
        });
        setInputs(defaults);
    };

    const getConversationName = () => {
        const current = conversations.find((item) => item.id === conversationId);
        return current?.name || 'Draft';
    };

    const getLastChatContent = () => {
        const lastAssistant = [...messages].reverse().find((msg) => msg.role === 'assistant');
        if (lastAssistant?.content) {
            return lastAssistant.content;
        }
        const lastUser = [...messages].reverse().find((msg) => msg.role === 'user');
        if (lastUser?.content) {
            return lastUser.content;
        }
        return instruction || '';
    };

    const createDraftFromContent = async (type, title, content) => {
        const path = type === 'page' ? '/wp/v2/pages' : '/wp/v2/posts';
        const data = await apiFetch({
            path,
            method: 'POST',
            data: {
                title: title || 'Draft',
                content,
                status: 'draft',
            },
        });
        setDraftId(data?.id || 0);
        if (data?.id) {
            const named = `${title || 'Draft'} (ID: ${data.id})`;
            await apiFetch({
                path: `${path}/${data.id}`,
                method: 'POST',
                data: { title: named },
            });
            setPreviewUrl(
                type === 'page'
                    ? `${window.location.origin}/?page_id=${data.id}&preview=true`
                    : `${window.location.origin}/?p=${data.id}&preview=true`
            );
        }
        return data;
    };

    const injectChatToEditor = () => {
        const content = getLastChatContent();
        if (!content) {
            logError('No chat content to inject.');
            return;
        }
        const title = editorTitle || getConversationName();
        let nextContent = '';
        if (injectMode === 'replace') {
            nextContent = content;
        } else if (injectMode === 'section') {
            nextContent = editorContent
                ? `${editorContent}\n\n## ${title}\n\n${content}`
                : `## ${title}\n\n${content}`;
        } else {
            nextContent = editorContent ? `${editorContent}\n\n${content}` : content;
        }
        setEditorTitle(title);
        setEditorContent(nextContent);
        setWorkspaceMode('editor');
        setChatToolStatus('Injected into editor.');
    };

    const createDraftFromChat = async () => {
        const content = getLastChatContent();
        if (!content) {
            logError('No chat content to create draft.');
            return;
        }
        const title = editorTitle || getConversationName();
        setChatToolStatus('');
        setEditorStatus('');
        setWorkspaceMode('editor');
        setEditorType(createType);
        setEditorTitle(title);
        setEditorContent(content);
        try {
            const data = await createDraftFromContent(createType, title, content);
            setEditorStatus(`Draft created: #${data.id}`);
            setChatToolStatus(`Draft created: #${data.id}`);
        } catch (err) {
            const message = err.message || 'Failed to create draft.';
            setEditorStatus(message);
            setChatToolStatus(message);
        }
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
                    logError(err.message || 'Failed to load agents.');
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
        setSelectedTools(agent?.tools || []);
    }, [agentId]);

    useEffect(() => {
        if (!isDragging) {
            return;
        }
        const handleMove = (event) => {
            const next = Math.min(640, Math.max(260, window.innerWidth - event.clientX - 24));
            setWorkspaceWidth(next);
        };
        const handleUp = () => {
            setIsDragging(false);
        };
        window.addEventListener('mousemove', handleMove);
        window.addEventListener('mouseup', handleUp);
        return () => {
            window.removeEventListener('mousemove', handleMove);
            window.removeEventListener('mouseup', handleUp);
        };
    }, [isDragging]);

    useEffect(() => {
        if (!nodeDrag) {
            return;
        }
        const handleMove = (event) => {
            const canvas = document.querySelector('.ai-studio-workflow-canvas');
            if (!canvas) {
                return;
            }
            const rect = canvas.getBoundingClientRect();
            const nextX = event.clientX - rect.left - nodeDrag.offsetX;
            const nextY = event.clientY - rect.top - nodeDrag.offsetY;
            setWorkflowNodes((prev) =>
                prev.map((node) =>
                    node.id === nodeDrag.id
                        ? {
                              ...node,
                              x: Math.max(20, nextX),
                              y: Math.max(20, nextY),
                          }
                        : node
                )
            );
        };
        const handleUp = () => {
            setNodeDrag(null);
            saveWorkflow(workflowNodesRef.current);
        };
        window.addEventListener('mousemove', handleMove);
        window.addEventListener('mouseup', handleUp);
        return () => {
            window.removeEventListener('mousemove', handleMove);
            window.removeEventListener('mouseup', handleUp);
        };
    }, [nodeDrag]);

    useEffect(() => {
        window.localStorage.setItem('ai_gateway_workspace_mode', workspaceMode);
    }, [workspaceMode]);

    useEffect(() => {
        window.localStorage.setItem('ai_gateway_workspace_width', String(workspaceWidth));
    }, [workspaceWidth]);

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
                    logError(err.message || 'Failed to load projects.');
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
                    path: `/ai/v1/conversations?project_id=${projectId}&include_archived=${showArchived ? '1' : '0'}`,
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
                    logError(err.message || 'Failed to load conversations.');
                }
            }
        };

        loadConversations();
        return () => {
            mounted = false;
        };
    }, [projectId, showArchived]);

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
                    logError(err.message || 'Failed to load conversation.');
                }
            }
        };

        loadConversation();
        return () => {
            mounted = false;
        };
    }, [conversationId]);

    useEffect(() => {
        if (!conversationId) {
            setWorkflowNodes([]);
            return;
        }
        let mounted = true;
        const loadWorkflow = async () => {
            try {
                const data = await apiFetch({
                    path: `/ai/v1/conversations/${conversationId}/workflow`,
                    method: 'GET',
                });
                if (mounted) {
                    setWorkflowNodes(normalizeWorkflowNodes(data?.workflow));
                    setWorkflowVersions(Array.isArray(data?.versions) ? data.versions : []);
                }
            } catch (err) {
                logError(err.message || 'Failed to load workflow.');
            }
        };

        loadWorkflow();
        return () => {
            mounted = false;
        };
    }, [conversationId]);

    useEffect(() => {
        workflowNodesRef.current = workflowNodes;
    }, [workflowNodes]);

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
            logError('No agent selected.');
            return;
        }

        if (useChain && chainAgents.length > 0) {
            await runAgentChain();
            return;
        }

        setIsBusy(true);
        setIsTyping(true);
        setError('');

        const fullInstruction = selectedTools.length
            ? `${instruction}\n\nTools: ${selectedTools.join(', ')}`
            : instruction;

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
        saveMessage(activeConversationId, 'user', fullInstruction);

        try {
            const base = settings.restUrl ? settings.restUrl.replace(/\/$/, '') : '/wp-json/ai/v1';
            const streamUrl = `${base}/run/stream`;
            const response = await fetch(streamUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce || '',
                },
                body: JSON.stringify({
                    agent_id: agentId,
                    instruction: fullInstruction,
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
                                logError(data.error);
                                return;
                            }
                            if (data.delta) {
                                updateAssistant(data.delta, false);
                            }
                            if (data.done && data.full) {
                                updateAssistant(data.full, true);
                                saveMessage(activeConversationId, 'assistant', data.full);
                                setIsTyping(false);
                            }
                        } catch (err) {
                            logError(err.message || 'Stream parse error.');
                        }
                    });
                });
            }
        } catch (err) {
            try {
                const data = await apiFetch({
                    path: '/ai/v1/run',
                    method: 'POST',
                    data: {
                        agent_id: agentId,
                        instruction: fullInstruction,
                        inputs,
                    },
                });
                if (data?.error) {
                    logError(data.error);
                } else {
                    const result = data?.mcp_response || data?.ollama_response || '';
                    updateAssistant(result, true);
                    saveMessage(activeConversationId, 'assistant', result);
                }
            } catch (fallbackError) {
                logError(fallbackError.message || err.message || 'Stream failed.');
            }
        } finally {
            setIsBusy(false);
            setInstruction('');
            setIsTyping(false);
        }
    };

    const runAgentChain = async () => {
        setIsBusy(true);
        setIsTyping(true);
        setError('');

        const fullInstruction = selectedTools.length
            ? `${instruction}\n\nTools: ${selectedTools.join(', ')}`
            : instruction;

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

        setMessages((prev) => [...prev, { role: 'user', content: instruction }]);
        saveMessage(activeConversationId, 'user', fullInstruction);

        try {
            for (const chainId of chainAgents) {
                const data = await apiFetch({
                    path: '/ai/v1/run',
                    method: 'POST',
                    data: {
                        agent_id: chainId,
                        instruction: fullInstruction,
                        inputs,
                    },
                });
                const result = data?.mcp_response || data?.ollama_response || data?.error || '';
                const label = agentById[chainId]?.name || 'Agent';
                const message = `${label}: ${result}`;
                setMessages((prev) => [...prev, { role: 'assistant', content: message }]);
                saveMessage(activeConversationId, 'assistant', message);
            }
        } catch (err) {
            logError(err.message || 'Chain failed.');
        } finally {
            setIsBusy(false);
            setIsTyping(false);
            setInstruction('');
        }
    };

    const saveWorkflow = async (nodes, options = {}) => {
        if (!conversationId) {
            return;
        }
        const payload = { workflow: nodes };
        if (options.saveVersion) {
            payload.save_version = true;
            payload.note = options.note || '';
        }
        const data = await apiFetch({
            path: `/ai/v1/conversations/${conversationId}/workflow`,
            method: 'POST',
            data: payload,
        });
        if (data?.versions) {
            setWorkflowVersions(data.versions);
        }
    };

    const runWorkflow = async () => {
        if (!workflowNodes.length) {
            logError('No workflow nodes.');
            return;
        }
        setWorkflowStatus('Running...');
        try {
            let lastOutput = '';
            for (const node of workflowNodes) {
                if (node.type === 'run_agent') {
                    const nodePrompt = node.prompt || instruction;
                    const data = await apiFetch({
                        path: '/ai/v1/run',
                        method: 'POST',
                        data: {
                            agent_id: node.agentId,
                            instruction: nodePrompt,
                            inputs,
                        },
                    });
                    lastOutput = data?.mcp_response || data?.ollama_response || '';
                }
                if (node.type === 'create_draft') {
                    const path = node.postType === 'page' ? '/wp/v2/pages' : '/wp/v2/posts';
                    const created = await apiFetch({
                        path,
                        method: 'POST',
                        data: {
                            title: node.title || 'Draft',
                            content: lastOutput,
                            status: 'draft',
                        },
                    });
                    setDraftId(created?.id || 0);
                    if (created?.id) {
                        const title = `${node.title || 'Draft'} (ID: ${created.id})`;
                        await apiFetch({
                            path: `${path}/${created.id}`,
                            method: 'POST',
                            data: { title },
                        });
                        setPreviewUrl(
                            node.postType === 'page'
                                ? `${window.location.origin}/?page_id=${created.id}&preview=true`
                                : `${window.location.origin}/?p=${created.id}&preview=true`
                        );
                    }
                }
                if (node.type === 'update_draft' && node.postId) {
                    const path = node.postType === 'page' ? `/wp/v2/pages/${node.postId}` : `/wp/v2/posts/${node.postId}`;
                    await apiFetch({
                        path,
                        method: 'POST',
                        data: {
                            content: lastOutput,
                        },
                    });
                }
                if (node.type === 'insert_image' && selectedMedia?.url) {
                    lastOutput += `\n\n![image](${selectedMedia.url})`;
                }
            }
            setWorkflowStatus('Done');
        } catch (err) {
            logError(err.message || 'Workflow failed.');
            setWorkflowStatus('Failed');
        }
    };

    useEffect(() => {
        if (!conversationId || messages.length === 0) {
            return;
        }
        const timer = setTimeout(async () => {
            try {
                await apiFetch({
                    path: `/ai/v1/conversations/${conversationId}/draft`,
                    method: 'POST',
                    data: { messages },
                });
            } catch (err) {
                logError(err.message || 'Failed to save draft.');
            }
        }, 1200);
        return () => clearTimeout(timer);
    }, [conversationId, messages]);

    const archiveConversation = async (id) => {
        await apiFetch({
            path: `/ai/v1/conversations/${id}/archive`,
            method: 'POST',
        });
        setConversations((prev) => prev.filter((item) => item.id !== id));
        if (conversationId === id) {
            setConversationId(0);
        }
    };

    const deleteConversation = async (id) => {
        await apiFetch({
            path: `/ai/v1/conversations/${id}`,
            method: 'DELETE',
        });
        setConversations((prev) => prev.filter((item) => item.id !== id));
        if (conversationId === id) {
            setConversationId(0);
        }
    };

    const moveConversation = async (id) => {
        let targetProjectId = moveProjectId;
        if (!targetProjectId && moveProjectName.trim() !== '') {
            const created = await apiFetch({
                path: '/ai/v1/projects',
                method: 'POST',
                data: { name: moveProjectName.trim() },
            });
            setProjects((prev) => [created, ...prev]);
            targetProjectId = created.id;
        }
        if (!targetProjectId) {
            return;
        }
        await apiFetch({
            path: `/ai/v1/conversations/${id}`,
            method: 'PUT',
            data: { project_id: targetProjectId },
        });
        setConversations((prev) => prev.filter((item) => item.id !== id));
        if (conversationId === id) {
            setConversationId(0);
        }
    };

    const workflowBounds = useMemo(() => {
        const maxX = workflowNodes.reduce((acc, node) => Math.max(acc, node.x || 0), 0);
        const maxY = workflowNodes.reduce((acc, node) => Math.max(acc, node.y || 0), 0);
        return {
            width: Math.max(520, maxX + WORKFLOW_NODE_WIDTH + 80),
            height: Math.max(360, maxY + WORKFLOW_NODE_HEIGHT + 80),
        };
    }, [workflowNodes]);

    const addWorkflowNode = (node) => {
        const index = workflowNodes.length;
        const next = [
            ...workflowNodes,
            {
                id: createNodeId(),
                x: 40 + index * 40,
                y: 40 + index * 100,
                ...node,
            },
        ];
        setWorkflowNodes(next);
        saveWorkflow(next);
    };

    const moveWorkflowNode = (index, direction) => {
        const next = [...workflowNodes];
        const target = index + direction;
        if (target < 0 || target >= next.length) {
            return;
        }
        const [moved] = next.splice(index, 1);
        next.splice(target, 0, moved);
        setWorkflowNodes(next);
        saveWorkflow(next);
    };

    const startNodeDrag = (event, node) => {
        if (event.button !== 0) {
            return;
        }
        event.preventDefault();
        const canvas = event.currentTarget.closest('.ai-studio-workflow-canvas');
        if (!canvas) {
            return;
        }
        const rect = canvas.getBoundingClientRect();
        setNodeDrag({
            id: node.id,
            offsetX: event.clientX - rect.left - (node.x || 0),
            offsetY: event.clientY - rect.top - (node.y || 0),
        });
    };

    return (
        <div className="ai-studio-app" style={{ gridTemplateColumns: `280px 1fr ${workspaceWidth}px` }}>
            <aside className="ai-studio-sidebar">
                <div className="ai-studio-sidebar-section">
                    <div className="ai-studio-sidebar-title">{__('Projects', 'ai-gateway')}</div>
                    <div className="ai-studio-project-row">
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
                        <button
                            type="button"
                            className="ai-studio-icon-button"
                            onClick={() => {
                                setActiveProjectActions(true);
                                const current = projects.find((item) => item.id === projectId);
                                setProjectName(current?.name || '');
                            }}
                        >
                            ...
                        </button>
                    </div>
                </div>
                <div className="ai-studio-sidebar-section">
                    <div className="ai-studio-sidebar-title">{__('Conversations', 'ai-gateway')}</div>
                    <label className="ai-studio-checkbox">
                        <input
                            type="checkbox"
                            checked={showArchived}
                            onChange={(event) => setShowArchived(event.target.checked)}
                        />
                        {__('Show archived', 'ai-gateway')}
                    </label>
                    <div className="ai-studio-conversation-list">
                        {conversations.map((item) => (
                            <div key={item.id} className="ai-studio-conversation-row">
                                <button
                                    type="button"
                                    className={`ai-studio-conversation ${item.id === conversationId ? 'active' : ''}`}
                                    onClick={() => setConversationId(item.id)}
                                >
                                    <div className="ai-studio-conversation-title">{item.name}</div>
                                    {item.archived && (
                                        <div className="ai-studio-conversation-last">{__('Archived', 'ai-gateway')}</div>
                                    )}
                                    {item.last && <div className="ai-studio-conversation-last">{item.last}</div>}
                                </button>
                                <button
                                    type="button"
                                    className="ai-studio-icon-button"
                                    onClick={() => {
                                        setActiveConversationActions(item.id);
                                        setMoveProjectId(0);
                                        setMoveProjectName('');
                                    }}
                                >
                                    ...
                                </button>
                            </div>
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
                    {agentTools.length === 0 && (
                        <div className="ai-studio-sidebar-empty">{__('Assigned via agent.', 'ai-gateway')}</div>
                    )}
                    {agentTools.map((tool) => (
                        <label key={tool} className="ai-studio-checkbox">
                            <input
                                type="checkbox"
                                checked={selectedTools.includes(tool)}
                                onChange={(event) => {
                                    const checked = event.target.checked;
                                    setSelectedTools((prev) => {
                                        if (checked) {
                                            return [...prev, tool];
                                        }
                                        return prev.filter((item) => item !== tool);
                                    });
                                }}
                            />
                            {tool}
                        </label>
                    ))}
                </div>
                <div className="ai-studio-sidebar-section">
                    <div className="ai-studio-sidebar-title">{__('Settings', 'ai-gateway')}</div>
                    <button
                        type="button"
                        className="ai-studio-button secondary"
                        onClick={() => setShowSettingsModal(true)}
                    >
                        {__('Open settings', 'ai-gateway')}
                    </button>
                    <button
                        type="button"
                        className="ai-studio-button secondary"
                        onClick={() => setWorkspaceMode('agents')}
                    >
                        {__('Agents editor', 'ai-gateway')}
                    </button>
                    <button
                        type="button"
                        className="ai-studio-button secondary"
                        onClick={() => setWorkspaceMode('admin')}
                    >
                        {__('WP Admin', 'ai-gateway')}
                    </button>
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
                        <div className="ai-studio-tabs">
                            {[
                            { id: 'chat', label: __('Chat', 'ai-gateway') },
                                { id: 'editor', label: __('Editor', 'ai-gateway') },
                                { id: 'split', label: __('Split', 'ai-gateway') },
                                { id: 'workflow', label: __('Workflow', 'ai-gateway') },
                                { id: 'preview', label: __('Preview', 'ai-gateway') },
                                { id: 'agents', label: __('Agents', 'ai-gateway') },
                                { id: 'admin', label: __('WP Admin', 'ai-gateway') },
                            ].map((tab) => (
                                <button
                                    key={tab.id}
                                    className={`ai-studio-tab ${workspaceMode === tab.id ? 'active' : ''}`}
                                    onClick={() => setWorkspaceMode(tab.id)}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
                <div className="ai-studio-chat">
                    {messages.map((msg, index) => (
                        <div key={index} className={`ai-studio-message ai-studio-${msg.role}`}>
                            <div className="ai-studio-message-role">{msg.role}</div>
                            <div className="ai-studio-message-content">{msg.content}</div>
                        </div>
                    ))}
                    {isTyping && (
                        <div className="ai-studio-typing">
                            {agent?.name || __('Agent', 'ai-gateway')} {__('is typing...', 'ai-gateway')}
                        </div>
                    )}
                </div>
                {errorLogs.length > 0 && (
                    <div className="ai-studio-error-log">
                        <strong>{__('Errors', 'ai-gateway')}</strong>
                        {errorLogs.map((item, index) => (
                            <div key={index} className="ai-studio-error-item">
                                {item.message}
                            </div>
                        ))}
                    </div>
                )}

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
                        <button
                            className="ai-studio-button icon"
                            type="button"
                            title={__('Add', 'ai-gateway')}
                            onClick={() => setShowPlusMenu((prev) => !prev)}
                        >
                            +
                        </button>
                        {showPlusMenu && (
                            <div className="ai-studio-plus-menu">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowPlusMenu(false);
                                        setWorkspaceMode('tools');
                                    }}
                                >
                                    {__('Tools', 'ai-gateway')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowPlusMenu(false);
                                        setWorkspaceMode('upload');
                                    }}
                                >
                                    {__('Upload image', 'ai-gateway')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowPlusMenu(false);
                                        setWorkspaceMode('chain');
                                    }}
                                >
                                    {__('Chain agents', 'ai-gateway')}
                                </button>
                            </div>
                        )}
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
                        <label className="ai-studio-checkbox">
                            <input
                                type="checkbox"
                                checked={useChain}
                                onChange={(event) => setUseChain(event.target.checked)}
                            />
                            {__('Use chain', 'ai-gateway')}
                        </label>
                    </div>
                    <div className="ai-studio-chat-tools">
                        <div className="ai-studio-sidebar-title">{__('Chat tools', 'ai-gateway')}</div>
                        <div className="ai-studio-chat-tools-row">
                            <select
                                className="ai-studio-select"
                                value={injectMode}
                                onChange={(event) => setInjectMode(event.target.value)}
                            >
                                <option value="append">{__('Append to editor', 'ai-gateway')}</option>
                                <option value="replace">{__('Replace editor', 'ai-gateway')}</option>
                                <option value="section">{__('Add as section', 'ai-gateway')}</option>
                            </select>
                            <button className="ai-studio-button secondary" onClick={injectChatToEditor}>
                                {__('Inject into editor', 'ai-gateway')}
                            </button>
                        </div>
                        <div className="ai-studio-chat-tools-row">
                            <select
                                className="ai-studio-select"
                                value={createType}
                                onChange={(event) => setCreateType(event.target.value)}
                            >
                                <option value="article">{__('Create article', 'ai-gateway')}</option>
                                <option value="page">{__('Create page', 'ai-gateway')}</option>
                            </select>
                            <button className="ai-studio-button" onClick={createDraftFromChat}>
                                {__('Create from chat', 'ai-gateway')}
                            </button>
                        </div>
                        {chatToolStatus && <div className="ai-studio-sidebar-empty">{chatToolStatus}</div>}
                    </div>
                </div>
            </main>
            <aside className="ai-studio-workspace">
                <div
                    className="ai-studio-resizer"
                    onMouseDown={() => setIsDragging(true)}
                    title={__('Resize', 'ai-gateway')}
                />
                {workspaceMode === 'agents' ? (
                    <iframe
                        title="Agents"
                        className="ai-studio-iframe"
                        src={`${adminBase}admin.php?page=ai-gateway-agents&action=edit&agent_id=${agentId}&studio=1`}
                    />
                ) : workspaceMode === 'admin' ? (
                    <iframe
                        title="WP Admin"
                        className="ai-studio-iframe"
                        src={`${adminBase}admin.php`}
                    />
                ) : workspaceMode === 'tools' ? (
                    <div className="ai-studio-workspace-empty">{__('Tools panel (coming soon)', 'ai-gateway')}</div>
                ) : workspaceMode === 'upload' ? (
                    <div className="ai-studio-workspace-panel">
                        <h3>{__('Upload image', 'ai-gateway')}</h3>
                        <button
                            className="ai-studio-button secondary"
                            onClick={() => {
                                if (!window.wp || !window.wp.media) {
                                    logError('Media library not available.');
                                    return;
                                }
                                const frame = window.wp.media({
                                    title: 'Select image',
                                    multiple: false,
                                    library: { type: 'image' },
                                });
                                frame.on('select', () => {
                                    const selection = frame.state().get('selection').first();
                                    if (!selection) {
                                        return;
                                    }
                                    const data = selection.toJSON();
                                    setSelectedMedia({ id: data.id, url: data.url });
                                });
                                frame.open();
                            }}
                        >
                            {__('Open Media Library', 'ai-gateway')}
                        </button>
                        <input
                            className="ai-studio-input"
                            value={uploadUrl}
                            onChange={(event) => setUploadUrl(event.target.value)}
                            placeholder={__('Image URL', 'ai-gateway')}
                        />
                        <button
                            className="ai-studio-button"
                            onClick={async () => {
                                setUploadStatus('');
                                try {
                                    const data = await apiFetch({
                                        path: '/ai/v1/media/import',
                                        method: 'POST',
                                        data: { url: uploadUrl },
                                    });
                                    setUploadStatus(data?.url ? `Imported: ${data.url}` : 'Imported');
                                    if (data?.url) {
                                        setSelectedMedia({ id: data.attachment_id, url: data.url });
                                    }
                                } catch (err) {
                                    setUploadStatus(err.message || 'Upload failed.');
                                }
                            }}
                        >
                            {__('Import', 'ai-gateway')}
                        </button>
                        {selectedMedia?.url && (
                            <button
                                className="ai-studio-button"
                                onClick={async () => {
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
                                    const content = `![image](${selectedMedia.url})`;
                                    setMessages((prev) => [...prev, { role: 'user', content }]);
                                    saveMessage(activeConversationId, 'user', content);
                                }}
                            >
                                {__('Insert into chat', 'ai-gateway')}
                            </button>
                        )}
                        {uploadStatus && <div className="ai-studio-sidebar-empty">{uploadStatus}</div>}
                    </div>
                ) : workspaceMode === 'chain' ? (
                    <div className="ai-studio-workspace-panel">
                        <h3>{__('Agent chain', 'ai-gateway')}</h3>
                        {agents.map((item) => (
                            <label key={item.id} className="ai-studio-checkbox">
                                <input
                                    type="checkbox"
                                    checked={chainAgents.includes(item.id)}
                                    onChange={(event) => {
                                        const checked = event.target.checked;
                                        setChainAgents((prev) => {
                                            if (checked) {
                                                return [...prev, item.id];
                                            }
                                            return prev.filter((id) => id !== item.id);
                                        });
                                    }}
                                />
                                {item.name}
                            </label>
                        ))}
                        <button className="ai-studio-button" onClick={runAgentChain} disabled={isBusy}>
                            {__('Run chain', 'ai-gateway')}
                        </button>
                    </div>
                ) : workspaceMode === 'editor' ? (
                    <div className="ai-studio-workspace-panel">
                        <h3>{__('Content editor', 'ai-gateway')}</h3>
                        <label className="ai-studio-field">
                            <span>{__('Type', 'ai-gateway')}</span>
                            <select
                                className="ai-studio-select"
                                value={editorType}
                                onChange={(event) => setEditorType(event.target.value)}
                            >
                                <option value="article">{__('Article', 'ai-gateway')}</option>
                                <option value="page">{__('Page', 'ai-gateway')}</option>
                                <option value="workflow">{__('Workflow', 'ai-gateway')}</option>
                            </select>
                        </label>
                        <label className="ai-studio-field">
                            <span>{__('Title', 'ai-gateway')}</span>
                            <input
                                className="ai-studio-input"
                                value={editorTitle}
                                onChange={(event) => setEditorTitle(event.target.value)}
                            />
                        </label>
                        <label className="ai-studio-field">
                            <span>{__('Content', 'ai-gateway')}</span>
                            <textarea
                                className="ai-studio-textarea editor"
                                rows="8"
                                value={editorContent}
                                onChange={(event) => setEditorContent(event.target.value)}
                            />
                        </label>
                        {editorStatus && <div className="ai-studio-sidebar-empty">{editorStatus}</div>}
                        {draftId > 0 && (
                            <div className="ai-studio-sidebar-empty">{`Draft ID: ${draftId}`}</div>
                        )}
                        <button
                            className="ai-studio-button"
                            onClick={async () => {
                                setEditorStatus('');
                                try {
                                    const data = await createDraftFromContent(
                                        editorType,
                                        editorTitle,
                                        editorContent
                                    );
                                    setEditorStatus(`Draft created: #${data.id}`);
                                } catch (err) {
                                    setEditorStatus(err.message || 'Failed to create draft.');
                                }
                            }}
                        >
                            {__('Create draft', 'ai-gateway')}
                        </button>
                        <button
                            className="ai-studio-button secondary"
                            onClick={() => {
                                const lastAssistant = [...messages].reverse().find((msg) => msg.role === 'assistant');
                                if (lastAssistant) {
                                    setEditorContent(lastAssistant.content);
                                }
                            }}
                        >
                            {__('Use last assistant', 'ai-gateway')}
                        </button>
                        {draftId > 0 && (
                            <button
                                className="ai-studio-button secondary"
                                onClick={async () => {
                                    setEditorStatus('');
                                    try {
                                        const path = editorType === 'page' ? `/wp/v2/pages/${draftId}` : `/wp/v2/posts/${draftId}`;
                                        await apiFetch({
                                            path,
                                            method: 'POST',
                                            data: {
                                                title: `${editorTitle || 'Draft'} (ID: ${draftId})`,
                                                content: editorContent,
                                            },
                                        });
                                        setEditorStatus('Draft updated.');
                                    } catch (err) {
                                        setEditorStatus(err.message || 'Failed to update draft.');
                                    }
                                }}
                            >
                                {__('Update draft', 'ai-gateway')}
                            </button>
                        )}
                        {previewUrl && (
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => setWorkspaceMode('preview')}
                            >
                                {__('Open preview', 'ai-gateway')}
                            </button>
                        )}
                    </div>
                ) : workspaceMode === 'split' ? (
                    <div className="ai-studio-workspace-panel split">
                        <div className="ai-studio-split-panel">
                            <h3>{__('Content editor', 'ai-gateway')}</h3>
                            <label className="ai-studio-field">
                                <span>{__('Type', 'ai-gateway')}</span>
                                <select
                                    className="ai-studio-select"
                                    value={editorType}
                                    onChange={(event) => setEditorType(event.target.value)}
                                >
                                    <option value="article">{__('Article', 'ai-gateway')}</option>
                                    <option value="page">{__('Page', 'ai-gateway')}</option>
                                    <option value="workflow">{__('Workflow', 'ai-gateway')}</option>
                                </select>
                            </label>
                            <label className="ai-studio-field">
                                <span>{__('Title', 'ai-gateway')}</span>
                                <input
                                    className="ai-studio-input"
                                    value={editorTitle}
                                    onChange={(event) => setEditorTitle(event.target.value)}
                                />
                            </label>
                            <label className="ai-studio-field">
                                <span>{__('Content', 'ai-gateway')}</span>
                                <textarea
                                    className="ai-studio-textarea editor"
                                    rows="8"
                                    value={editorContent}
                                    onChange={(event) => setEditorContent(event.target.value)}
                                />
                            </label>
                            {editorStatus && <div className="ai-studio-sidebar-empty">{editorStatus}</div>}
                            {draftId > 0 && (
                                <div className="ai-studio-sidebar-empty">{`Draft ID: ${draftId}`}</div>
                            )}
                            <button
                                className="ai-studio-button"
                                onClick={async () => {
                                    setEditorStatus('');
                                    try {
                                        const data = await createDraftFromContent(
                                            editorType,
                                            editorTitle,
                                            editorContent
                                        );
                                        setEditorStatus(`Draft created: #${data.id}`);
                                    } catch (err) {
                                        setEditorStatus(err.message || 'Failed to create draft.');
                                    }
                                }}
                            >
                                {__('Create draft', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => {
                                    const lastAssistant = [...messages].reverse().find((msg) => msg.role === 'assistant');
                                    if (lastAssistant) {
                                        setEditorContent(lastAssistant.content);
                                    }
                                }}
                            >
                                {__('Use last assistant', 'ai-gateway')}
                            </button>
                            {draftId > 0 && (
                                <button
                                    className="ai-studio-button secondary"
                                    onClick={async () => {
                                        setEditorStatus('');
                                        try {
                                            const path = editorType === 'page' ? `/wp/v2/pages/${draftId}` : `/wp/v2/posts/${draftId}`;
                                            await apiFetch({
                                                path,
                                                method: 'POST',
                                                data: {
                                                    title: `${editorTitle || 'Draft'} (ID: ${draftId})`,
                                                    content: editorContent,
                                                },
                                            });
                                            setEditorStatus('Draft updated.');
                                        } catch (err) {
                                            setEditorStatus(err.message || 'Failed to update draft.');
                                        }
                                    }}
                                >
                                    {__('Update draft', 'ai-gateway')}
                                </button>
                            )}
                        </div>
                        <div className="ai-studio-split-panel">
                            <h3>{__('Preview', 'ai-gateway')}</h3>
                            {previewUrl ? (
                                <iframe title="Preview" className="ai-studio-iframe" src={previewUrl} />
                            ) : (
                                <div className="ai-studio-workspace-empty">{__('No preview yet.', 'ai-gateway')}</div>
                            )}
                        </div>
                    </div>
                ) : workspaceMode === 'workflow' ? (
                    <div className="ai-studio-workspace-panel">
                        <div className="ai-studio-workflow-header">
                            <h3>{__('Workflow builder', 'ai-gateway')}</h3>
                            <div className="ai-studio-workflow-meta">
                                <input
                                    className="ai-studio-input"
                                    value={workflowNote}
                                    placeholder={__('Version note (optional)', 'ai-gateway')}
                                    onChange={(event) => setWorkflowNote(event.target.value)}
                                />
                                <button
                                    className="ai-studio-button secondary"
                                    onClick={() => {
                                        saveWorkflow(workflowNodes, { saveVersion: true, note: workflowNote });
                                        setWorkflowNote('');
                                    }}
                                >
                                    {__('Save version', 'ai-gateway')}
                                </button>
                            </div>
                        </div>
                        {workflowVersions.length > 0 && (
                            <div className="ai-studio-workflow-versions">
                                {workflowVersions.map((version, index) => (
                                    <div key={index} className="ai-studio-workflow-version">
                                        <div>
                                            <div className="ai-studio-workflow-version-title">
                                                {`Version ${workflowVersions.length - index}`}
                                            </div>
                                            <div className="ai-studio-workflow-version-meta">
                                                {version.note || __('No note', 'ai-gateway')}
                                                {' · '}
                                                {version.saved_at ? new Date(version.saved_at).toLocaleString() : ''}
                                            </div>
                                        </div>
                                        <button
                                            className="ai-studio-button secondary tiny"
                                            onClick={() => {
                                                const restored = normalizeWorkflowNodes(version.workflow || []);
                                                setWorkflowNodes(restored);
                                                saveWorkflow(restored);
                                            }}
                                        >
                                            {__('Restore', 'ai-gateway')}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                        <div
                            className="ai-studio-workflow-canvas"
                            style={{ width: workflowBounds.width, height: workflowBounds.height }}
                        >
                            <svg
                                className="ai-studio-workflow-lines"
                                width={workflowBounds.width}
                                height={workflowBounds.height}
                            >
                                {workflowNodes.map((node, index) => {
                                    const next = workflowNodes[index + 1];
                                    if (!next) {
                                        return null;
                                    }
                                    const startX = (node.x || 0) + WORKFLOW_NODE_WIDTH;
                                    const startY = (node.y || 0) + WORKFLOW_NODE_HEIGHT / 2;
                                    const endX = next.x || 0;
                                    const endY = (next.y || 0) + WORKFLOW_NODE_HEIGHT / 2;
                                    return (
                                        <line
                                            key={`${node.id}-line`}
                                            x1={startX}
                                            y1={startY}
                                            x2={endX}
                                            y2={endY}
                                            stroke="#334155"
                                            strokeWidth="2"
                                        />
                                    );
                                })}
                            </svg>
                            {workflowNodes.map((node, index) => (
                                <div
                                    key={node.id}
                                    className="ai-studio-workflow-node"
                                    style={{ left: node.x, top: node.y, width: WORKFLOW_NODE_WIDTH }}
                                >
                                    <div
                                        className="ai-studio-workflow-node-header"
                                        onMouseDown={(event) => startNodeDrag(event, node)}
                                    >
                                        <span>{`#${index + 1} ${node.type}`}</span>
                                        <div className="ai-studio-workflow-node-actions">
                                            <button
                                                className="ai-studio-button secondary tiny"
                                                onClick={() => moveWorkflowNode(index, -1)}
                                            >
                                                {__('Up', 'ai-gateway')}
                                            </button>
                                            <button
                                                className="ai-studio-button secondary tiny"
                                                onClick={() => moveWorkflowNode(index, 1)}
                                            >
                                                {__('Down', 'ai-gateway')}
                                            </button>
                                            <button
                                                className="ai-studio-button secondary tiny"
                                                onClick={() => {
                                                    const next = workflowNodes.filter((_, i) => i !== index);
                                                    setWorkflowNodes(next);
                                                    saveWorkflow(next);
                                                }}
                                            >
                                                {__('Remove', 'ai-gateway')}
                                            </button>
                                        </div>
                                    </div>
                                    <div className="ai-studio-workflow-node-body">
                                        {node.type === 'run_agent' && (
                                            <select
                                                className="ai-studio-select"
                                                value={node.agentId || ''}
                                                onChange={(event) => {
                                                    const next = [...workflowNodes];
                                                    next[index] = { ...node, agentId: Number(event.target.value) };
                                                    setWorkflowNodes(next);
                                                    saveWorkflow(next);
                                                }}
                                            >
                                                <option value="">{__('Select agent', 'ai-gateway')}</option>
                                                {agents.map((item) => (
                                                    <option key={item.id} value={item.id}>
                                                        {item.name}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                        {node.type === 'run_agent' && (
                                            <textarea
                                                className="ai-studio-textarea editor"
                                                rows="3"
                                                placeholder={__('Prompt (optional)', 'ai-gateway')}
                                                value={node.prompt || ''}
                                                onChange={(event) => {
                                                    const next = [...workflowNodes];
                                                    next[index] = { ...node, prompt: event.target.value };
                                                    setWorkflowNodes(next);
                                                    saveWorkflow(next);
                                                }}
                                            />
                                        )}
                                        {(node.type === 'create_draft' || node.type === 'update_draft') && (
                                            <select
                                                className="ai-studio-select"
                                                value={node.postType || 'article'}
                                                onChange={(event) => {
                                                    const next = [...workflowNodes];
                                                    next[index] = { ...node, postType: event.target.value };
                                                    setWorkflowNodes(next);
                                                    saveWorkflow(next);
                                                }}
                                            >
                                                <option value="article">{__('Article', 'ai-gateway')}</option>
                                                <option value="page">{__('Page', 'ai-gateway')}</option>
                                            </select>
                                        )}
                                        {node.type === 'create_draft' && (
                                            <input
                                                className="ai-studio-input"
                                                value={node.title || ''}
                                                placeholder={__('Draft title', 'ai-gateway')}
                                                onChange={(event) => {
                                                    const next = [...workflowNodes];
                                                    next[index] = { ...node, title: event.target.value };
                                                    setWorkflowNodes(next);
                                                    saveWorkflow(next);
                                                }}
                                            />
                                        )}
                                        {node.type === 'update_draft' && (
                                            <input
                                                className="ai-studio-input"
                                                value={node.postId || ''}
                                                placeholder={__('Draft ID', 'ai-gateway')}
                                                onChange={(event) => {
                                                    const next = [...workflowNodes];
                                                    next[index] = { ...node, postId: Number(event.target.value) };
                                                    setWorkflowNodes(next);
                                                    saveWorkflow(next);
                                                }}
                                            />
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="ai-studio-workflow-actions">
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => addWorkflowNode({ type: 'run_agent', agentId: agentId || 0 })}
                            >
                                {__('Add Agent', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => addWorkflowNode({ type: 'create_draft', postType: 'article', title: '' })}
                            >
                                {__('Add Draft', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => addWorkflowNode({ type: 'update_draft', postType: 'article', postId: 0 })}
                            >
                                {__('Update Draft', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => addWorkflowNode({ type: 'insert_image' })}
                            >
                                {__('Insert Image', 'ai-gateway')}
                            </button>
                        </div>
                        <button className="ai-studio-button" onClick={runWorkflow} disabled={isBusy}>
                            {__('Run workflow', 'ai-gateway')}
                        </button>
                        {previewUrl && (
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => setWorkspaceMode('preview')}
                            >
                                {__('Open preview', 'ai-gateway')}
                            </button>
                        )}
                        {workflowStatus && <div className="ai-studio-sidebar-empty">{workflowStatus}</div>}
                    </div>
                ) : workspaceMode === 'preview' ? (
                    <div className="ai-studio-workspace-panel preview">
                        <h3>{__('Preview', 'ai-gateway')}</h3>
                        {previewUrl ? (
                            <iframe title="Preview" className="ai-studio-iframe" src={previewUrl} />
                        ) : (
                            <div className="ai-studio-workspace-empty">{__('No preview yet.', 'ai-gateway')}</div>
                        )}
                    </div>
                ) : (
                    <div className="ai-studio-workspace-empty">{__('Workspace', 'ai-gateway')}</div>
                )}
            </aside>
            {activeConversationActions && (
                <div className="ai-studio-modal">
                    <div className="ai-studio-modal-card">
                        <h3>{__('Conversation actions', 'ai-gateway')}</h3>
                        <label className="ai-studio-field">
                            <span>{__('Rename', 'ai-gateway')}</span>
                            <input
                                value={
                                    conversations.find((item) => item.id === activeConversationActions)?.name || ''
                                }
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setConversations((prev) =>
                                        prev.map((item) =>
                                            item.id === activeConversationActions ? { ...item, name: value } : item
                                        )
                                    );
                                }}
                            />
                        </label>
                        <label className="ai-studio-field">
                            <span>{__('Move to project', 'ai-gateway')}</span>
                            <select
                                className="ai-studio-select"
                                value={moveProjectId}
                                onChange={(event) => setMoveProjectId(Number(event.target.value))}
                            >
                                <option value="0">{__('Select project', 'ai-gateway')}</option>
                                {projects.map((project) => (
                                    <option key={project.id} value={project.id}>
                                        {project.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="ai-studio-field">
                            <span>{__('Or create project', 'ai-gateway')}</span>
                            <input
                                value={moveProjectName}
                                onChange={(event) => setMoveProjectName(event.target.value)}
                                placeholder={__('New project name', 'ai-gateway')}
                            />
                        </label>
                        <div className="ai-studio-modal-actions">
                            <button
                                className="ai-studio-button secondary"
                                onClick={async () => {
                                    const name =
                                        conversations.find((item) => item.id === activeConversationActions)?.name ||
                                        '';
                                    await apiFetch({
                                        path: `/ai/v1/conversations/${activeConversationActions}`,
                                        method: 'PUT',
                                        data: { name },
                                    });
                                    setActiveConversationActions(null);
                                }}
                            >
                                {__('Rename', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button secondary"
                                onClick={async () => {
                                    await moveConversation(activeConversationActions);
                                    setActiveConversationActions(null);
                                }}
                            >
                                {__('Move', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button secondary"
                                onClick={async () => {
                                    await archiveConversation(activeConversationActions);
                                    setActiveConversationActions(null);
                                }}
                            >
                                {__('Archive', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button"
                                onClick={async () => {
                                    await deleteConversation(activeConversationActions);
                                    setActiveConversationActions(null);
                                }}
                            >
                                {__('Delete', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => setActiveConversationActions(null)}
                            >
                                {__('Cancel', 'ai-gateway')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
            {activeProjectActions && (
                <div className="ai-studio-modal">
                    <div className="ai-studio-modal-card">
                        <h3>{__('Project actions', 'ai-gateway')}</h3>
                        <label className="ai-studio-field">
                            <span>{__('Rename', 'ai-gateway')}</span>
                            <input
                                value={projectName}
                                onChange={(event) => setProjectName(event.target.value)}
                            />
                        </label>
                        <div className="ai-studio-modal-actions">
                            <button
                                className="ai-studio-button secondary"
                                onClick={async () => {
                                    await apiFetch({
                                        path: `/ai/v1/projects/${projectId}`,
                                        method: 'PUT',
                                        data: { name: projectName },
                                    });
                                    setProjects((prev) =>
                                        prev.map((item) =>
                                            item.id === projectId ? { ...item, name: projectName } : item
                                        )
                                    );
                                    setActiveProjectActions(false);
                                }}
                            >
                                {__('Rename', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button"
                                onClick={async () => {
                                    await apiFetch({
                                        path: `/ai/v1/projects/${projectId}`,
                                        method: 'DELETE',
                                    });
                                    setProjects((prev) => prev.filter((item) => item.id !== projectId));
                                    setProjectId(0);
                                    setActiveProjectActions(false);
                                }}
                            >
                                {__('Delete', 'ai-gateway')}
                            </button>
                            <button
                                className="ai-studio-button secondary"
                                onClick={() => setActiveProjectActions(false)}
                            >
                                {__('Cancel', 'ai-gateway')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
            {showSettingsModal && (
                <div className="ai-studio-modal">
                    <div className="ai-studio-modal-card large">
                        <div className="ai-studio-modal-header">
                            <h3>{__('Settings', 'ai-gateway')}</h3>
                            <button className="ai-studio-icon-button" onClick={() => setShowSettingsModal(false)}>
                                X
                            </button>
                        </div>
                        <iframe
                            title="Settings"
                            className="ai-studio-iframe"
                            src={`${adminBase}admin.php?page=ai-gateway-settings&studio=1`}
                        />
                    </div>
                </div>
            )}
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
