import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar } from '@wordpress/edit-post';
import {
    PanelBody,
    SelectControl,
    TextareaControl,
    TextControl,
    Button,
    Notice,
    Spinner,
    ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { dispatch, select, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

const settings = window.AIGatewaySettings || { restUrl: '', nonce: '', agents: [] };

if (settings.nonce) {
    apiFetch.use(apiFetch.createNonceMiddleware(settings.nonce));
}

const QUICK_ACTIONS = [
    {
        key: 'rewrite',
        label: __('Rewrite', 'ai-gateway'),
        instruction: __('Rewrite the text to be clearer and more engaging.', 'ai-gateway'),
    },
    {
        key: 'seo',
        label: __('SEO', 'ai-gateway'),
        instruction: __('Optimize the content for SEO while keeping a natural tone.', 'ai-gateway'),
    },
    {
        key: 'summarize',
        label: __('Summarize', 'ai-gateway'),
        instruction: __('Summarize the content in a few bullet points.', 'ai-gateway'),
    },
    {
        key: 'outline',
        label: __('Outline', 'ai-gateway'),
        instruction: __('Generate a structured outline for this article.', 'ai-gateway'),
    },
];

const buildBlocksFromJson = (blocksJson) => {
    const toBlock = (item) => {
        if (!item || !item.name) {
            return null;
        }
        const innerBlocks = (item.innerBlocks || []).map(toBlock).filter(Boolean);
        return createBlock(item.name, item.attributes || {}, innerBlocks);
    };

    return blocksJson.map(toBlock).filter(Boolean);
};

const stripTags = (value) => String(value || '').replace(/<[^>]+>/g, '').trim();

const getBlockLabel = (block) => {
    const name = block.name.replace('core/', '');
    const content = stripTags(block.attributes?.content || block.attributes?.title || '');
    if (!content) {
        return name;
    }
    return `${name}: ${content.slice(0, 40)}`;
};

const insertBlocksAfterSelection = (blocks) => {
    const selectedBlock = select('core/block-editor').getSelectedBlock();
    if (selectedBlock) {
        const index = select('core/block-editor').getBlockIndex(selectedBlock.clientId);
        dispatch('core/block-editor').insertBlocks(blocks, index + 1);
    } else {
        dispatch('core/block-editor').insertBlocks(blocks);
    }
};

const createButtonBlock = (text, url) =>
    createBlock('core/buttons', {}, [createBlock('core/button', { text, url })]);

function AIGatewaySidebar() {
    const [agents, setAgents] = useState(settings.agents || []);
    const [agentId, setAgentId] = useState(settings.agents?.[0]?.id || 0);
    const [instruction, setInstruction] = useState('');
    const [inputs, setInputs] = useState({});
    const [output, setOutput] = useState('');
    const [meta, setMeta] = useState('');
    const [isBusy, setIsBusy] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [missingModel, setMissingModel] = useState('');
    const [isPullingModel, setIsPullingModel] = useState(false);
    const [pullStatus, setPullStatus] = useState('');

    const blocks = useSelect((store) => store('core/block-editor').getBlocks(), []);
    const selectedClientId = useSelect(
        (store) => store('core/block-editor').getSelectedBlockClientId(),
        []
    );

    const [targetBlockId, setTargetBlockId] = useState(selectedClientId || '');
    const [smartContent, setSmartContent] = useState('');
    const [smartTextColor, setSmartTextColor] = useState('');
    const [smartBgColor, setSmartBgColor] = useState('');
    const [smartError, setSmartError] = useState('');

    const [outlineText, setOutlineText] = useState('');
    const [templateType, setTemplateType] = useState('landing');
    const [faqText, setFaqText] = useState('');
    const [ctaTitle, setCtaTitle] = useState('Get Started');
    const [ctaBody, setCtaBody] = useState('Ready to launch?');
    const [ctaButtonText, setCtaButtonText] = useState('Contact');
    const [ctaButtonUrl, setCtaButtonUrl] = useState('#');
    const [paletteTextColor, setPaletteTextColor] = useState('');
    const [paletteBgColor, setPaletteBgColor] = useState('');
    const [paletteAllBlocks, setPaletteAllBlocks] = useState(false);
    const [heroTitle, setHeroTitle] = useState('Hero Title');
    const [heroSubtitle, setHeroSubtitle] = useState('Short supporting copy goes here.');
    const [heroButtonText, setHeroButtonText] = useState('Learn more');
    const [heroButtonUrl, setHeroButtonUrl] = useState('#');
    const [heroImage, setHeroImage] = useState(null);

    const agent = useMemo(
        () => agents.find((item) => item.id === agentId),
        [agents, agentId]
    );

    const targetBlock = useMemo(
        () => blocks.find((block) => block.clientId === targetBlockId),
        [blocks, targetBlockId]
    );

    const rawSchema = agent?.input_schema || [];
    const inputSchema = rawSchema
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
    const visibleSchema = inputSchema.filter((field) => !field.env);
    const outputMode = agent?.output_mode || 'text';
    const agentTools = agent?.tools || [
        'smart_edit',
        'outline_sections',
        'page_template',
        'faq_builder',
        'cta_builder',
        'quick_palette',
        'media_insert',
        'hero_section',
    ];

    const hasTool = (toolId) => agentTools.includes(toolId);

    const resetInputs = () => {
        const defaults = {};
        visibleSchema.forEach((field) => {
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
            setIsLoading(true);
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
                setAgentId(list[0]?.id || 0);
            } catch (err) {
                if (mounted) {
                    setError(err.message || 'Failed to load agents.');
                }
            } finally {
                if (mounted) {
                    setIsLoading(false);
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
        if (selectedClientId && selectedClientId !== targetBlockId) {
            setTargetBlockId(selectedClientId);
        }
    }, [selectedClientId, targetBlockId]);

    useEffect(() => {
        if (!targetBlock) {
            setSmartContent('');
            return;
        }
        const content = targetBlock.attributes?.content || targetBlock.attributes?.title || '';
        setSmartContent(stripTags(content));
    }, [targetBlock]);

    const handleInputChange = (field, value) => {
        setInputs((prev) => ({ ...prev, [field]: value }));
    };

    const applyQuickAction = (action) => {
        setInstruction(action.instruction);
        if (action.inputs) {
            setInputs((prev) => ({ ...prev, ...action.inputs }));
        }
    };

    const runAgentFallback = async () => {
        const data = await apiFetch({
            path: '/ai/v1/run',
            method: 'POST',
            data: {
                agent_id: agentId,
                instruction,
                inputs,
            },
        });

        if (data?.error) {
            if (data.error === 'model_not_found') {
                setMissingModel(data.model || '');
                setError('Model not found.');
                return;
            }
            setError(data.error);
            setOutput('');
        } else {
            setOutput(data?.mcp_response || data?.ollama_response || '');
            setMeta(data?.mcp_meta || '');
        }
    };

    const runAgent = async () => {
        if (!agentId) {
            setError('No agent selected.');
            return;
        }

        setIsBusy(true);
        setError('');
        setMeta('');
        setOutput(__('Running...', 'ai-gateway'));

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

            setOutput('');

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
                                if (data.error === 'model_not_found') {
                                    setMissingModel(data.model || '');
                                    setError('Model not found.');
                                    return;
                                }
                                setError(data.error);
                                return;
                            }
                            if (data.delta) {
                                setOutput((prev) => prev + data.delta);
                            }
                            if (data.mcp_meta) {
                                setMeta(data.mcp_meta);
                            }
                            if (data.done && data.full) {
                                setOutput(data.full);
                                if (data.mcp_response) {
                                    setMeta(data.mcp_meta || '');
                                }
                            }
                        } catch (err) {
                            setError(err.message || 'Stream parse error.');
                        }
                    });
                });
            }
        } catch (err) {
            await runAgentFallback();
        } finally {
            setIsBusy(false);
        }
    };

    const pullModel = async () => {
        if (!missingModel) {
            return;
        }
        setIsPullingModel(true);
        setPullStatus(__('Downloading model...', 'ai-gateway'));
        try {
            const response = await fetch('/wp-json/ai/v1/ollama/pull/stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce || '',
                },
                body: JSON.stringify({ model: missingModel }),
            });

            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !response.body || !contentType.includes('text/event-stream')) {
                throw new Error('Pull stream unavailable');
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
                                setPullStatus(data.error);
                                return;
                            }
                            if (data.status) {
                                setPullStatus(data.status);
                            }
                            if (data.completed && data.total) {
                                const pct = Math.round((data.completed / data.total) * 100);
                                setPullStatus(`${data.status || 'Downloading'} (${pct}%)`);
                            }
                        } catch (err) {
                            setPullStatus(err.message || 'Pull parse error.');
                        }
                    });
                });
            }
        } catch (err) {
            setPullStatus(err.message || 'Pull failed.');
        } finally {
            setIsPullingModel(false);
        }
    };

    const injectOutput = () => {
        if (!output) {
            return;
        }

        if (outputMode === 'blocks') {
            try {
                const parsed = JSON.parse(output);
                const blocksJson = Array.isArray(parsed) ? parsed : parsed.blocks || [];
                const blocksToInsert = buildBlocksFromJson(blocksJson);
                if (blocksToInsert.length) {
                    insertBlocksAfterSelection(blocksToInsert);
                }
            } catch (err) {
                setError(err.message || 'Failed to parse blocks.');
            }
            return;
        }

        const selectedBlock = select('core/block-editor').getSelectedBlock();
        if (selectedBlock && Object.prototype.hasOwnProperty.call(selectedBlock.attributes, 'content')) {
            dispatch('core/block-editor').updateBlockAttributes(selectedBlock.clientId, { content: output });
            return;
        }

        insertBlocksAfterSelection([createBlock('core/paragraph', { content: output })]);
    };

    const applySmartEdit = () => {
        if (!targetBlock) {
            setSmartError('Select a block first.');
            return;
        }

        const attrs = { ...targetBlock.attributes };
        if (Object.prototype.hasOwnProperty.call(attrs, 'content')) {
            attrs.content = smartContent;
        } else if (Object.prototype.hasOwnProperty.call(attrs, 'title')) {
            attrs.title = smartContent;
        } else {
            setSmartError('Selected block has no editable text.');
            return;
        }

        const style = { ...(attrs.style || {}) };
        if (smartTextColor) {
            style.color = { ...(style.color || {}), text: smartTextColor };
        }
        if (smartBgColor) {
            style.color = { ...(style.color || {}), background: smartBgColor };
        }
        if (smartTextColor || smartBgColor) {
            attrs.style = style;
        }

        dispatch('core/block-editor').updateBlockAttributes(targetBlock.clientId, attrs);
        setSmartError('');
    };

    const outlineToSections = () => {
        const lines = outlineText
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);

        if (!lines.length) {
            return;
        }

        const blocksToInsert = [];
        lines.forEach((line) => {
            let level = 2;
            let text = line;
            if (line.startsWith('### ')) {
                level = 3;
                text = line.slice(4);
            } else if (line.startsWith('## ')) {
                level = 2;
                text = line.slice(3);
            } else if (line.startsWith('# ')) {
                level = 2;
                text = line.slice(2);
            }

            blocksToInsert.push(createBlock('core/heading', { level, content: text }));
            blocksToInsert.push(createBlock('core/paragraph', { content: '' }));
        });

        insertBlocksAfterSelection(blocksToInsert);
    };

    const buildTemplateBlocks = (type) => {
        if (type === 'landing') {
            return [
                createBlock('core/heading', { level: 1, content: 'Landing Page Title' }),
                createBlock('core/paragraph', { content: 'Short value proposition goes here.' }),
                createBlock('core/columns', {}, [
                    createBlock('core/column', {}, [
                        createBlock('core/heading', { level: 3, content: 'Feature One' }),
                        createBlock('core/paragraph', { content: 'Feature description.' }),
                    ]),
                    createBlock('core/column', {}, [
                        createBlock('core/heading', { level: 3, content: 'Feature Two' }),
                        createBlock('core/paragraph', { content: 'Feature description.' }),
                    ]),
                ]),
                createButtonBlock('Get Started', '#'),
            ];
        }

        if (type === 'blog') {
            return [
                createBlock('core/heading', { level: 1, content: 'Blog Post Title' }),
                createBlock('core/paragraph', { content: 'Intro paragraph.' }),
                createBlock('core/heading', { level: 2, content: 'Section One' }),
                createBlock('core/paragraph', { content: 'Section content.' }),
                createBlock('core/heading', { level: 2, content: 'Section Two' }),
                createBlock('core/paragraph', { content: 'Section content.' }),
            ];
        }

        return [
            createBlock('core/heading', { level: 1, content: 'Service Page' }),
            createBlock('core/paragraph', { content: 'Service overview.' }),
            createBlock('core/heading', { level: 2, content: 'Benefits' }),
            createBlock('core/list', { values: '<li>Benefit one</li><li>Benefit two</li>' }),
            createButtonBlock('Contact Us', '#'),
        ];
    };

    const insertTemplate = () => {
        insertBlocksAfterSelection(buildTemplateBlocks(templateType));
    };

    const insertFaq = () => {
        const lines = faqText
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);

        const pairs = [];
        let current = null;
        lines.forEach((line) => {
            if (line.toLowerCase().startsWith('q:')) {
                current = { q: line.slice(2).trim(), a: '' };
                pairs.push(current);
                return;
            }
            if (line.toLowerCase().startsWith('a:')) {
                if (current) {
                    current.a = line.slice(2).trim();
                }
                return;
            }
            if (current && !current.a) {
                current.a = line;
            }
        });

        if (!pairs.length) {
            return;
        }

        const blocksToInsert = [createBlock('core/heading', { level: 2, content: 'FAQ' })];
        pairs.forEach((item) => {
            blocksToInsert.push(createBlock('core/heading', { level: 3, content: item.q || 'Question' }));
            blocksToInsert.push(createBlock('core/paragraph', { content: item.a || '' }));
        });

        insertBlocksAfterSelection(blocksToInsert);
    };

    const insertCta = () => {
        const blocksToInsert = [
            createBlock('core/group', {}, [
                createBlock('core/heading', { level: 2, content: ctaTitle }),
                createBlock('core/paragraph', { content: ctaBody }),
                createButtonBlock(ctaButtonText, ctaButtonUrl),
            ]),
        ];

        insertBlocksAfterSelection(blocksToInsert);
    };

    const applyPalette = () => {
        const targets = paletteAllBlocks ? blocks : targetBlock ? [targetBlock] : [];
        if (!targets.length) {
            return;
        }

        targets.forEach((block) => {
            const attrs = { ...block.attributes };
            const style = { ...(attrs.style || {}) };
            if (paletteTextColor) {
                style.color = { ...(style.color || {}), text: paletteTextColor };
            }
            if (paletteBgColor) {
                style.color = { ...(style.color || {}), background: paletteBgColor };
            }
            attrs.style = style;
            dispatch('core/block-editor').updateBlockAttributes(block.clientId, attrs);
        });
    };

    const insertImage = (media) => {
        if (!media || !media.url) {
            return;
        }
        const imageBlock = createBlock('core/image', {
            id: media.id,
            url: media.url,
            alt: media.alt || '',
        });
        insertBlocksAfterSelection([imageBlock]);
    };

    const insertHero = () => {
        const coverAttrs = {
            url: heroImage?.url || undefined,
            dimRatio: 30,
        };

        const heroBlocks = [
            createBlock('core/heading', { level: 1, content: heroTitle }),
            createBlock('core/paragraph', { content: heroSubtitle }),
            createButtonBlock(heroButtonText, heroButtonUrl),
        ];

        const coverBlock = createBlock('core/cover', coverAttrs, heroBlocks);
        insertBlocksAfterSelection([coverBlock]);
    };

    const agentOptions = agents.map((item) => ({ label: item.name, value: item.id }));
    const blockOptions = blocks.map((block) => ({
        label: getBlockLabel(block),
        value: block.clientId,
    }));

    return (
        <PluginSidebar name="ai-gateway" title={__('AI Agent', 'ai-gateway')}>
            <PanelBody title={__('AI Assistant', 'ai-gateway')}> 
                {isLoading && <Spinner />}

                {error && (
                    <Notice status="error" isDismissible={false}>
                        {error}
                        {missingModel && (
                            <div style={{ marginTop: '8px' }}>
                                <div>{missingModel}</div>
                                <Button
                                    variant="secondary"
                                    onClick={pullModel}
                                    isBusy={isPullingModel}
                                    style={{ marginTop: '6px' }}
                                >
                                    {__('Download model', 'ai-gateway')}
                                </Button>
                                {pullStatus && <div style={{ marginTop: '6px' }}>{pullStatus}</div>}
                            </div>
                        )}
                    </Notice>
                )}

                <SelectControl
                    label={__('Agent', 'ai-gateway')}
                    value={agentId}
                    options={agentOptions}
                    onChange={(value) => setAgentId(Number(value))}
                />

                <TextareaControl
                    label={__('Instruction', 'ai-gateway')}
                    value={instruction}
                    onChange={setInstruction}
                />

                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', marginBottom: '12px' }}>
                    {QUICK_ACTIONS.map((action) => (
                        <Button key={action.key} variant="secondary" onClick={() => applyQuickAction(action)}>
                            {action.label}
                        </Button>
                    ))}
                </div>

                {visibleSchema.map((field) => {
                    if (field.type === 'textarea') {
                        return (
                            <TextareaControl
                                key={field.key}
                                label={field.label}
                                value={inputs[field.key] || ''}
                                onChange={(value) => handleInputChange(field.key, value)}
                                help={field.placeholder}
                            />
                        );
                    }
                    if (field.type === 'select') {
                        const options = Array.isArray(field.options)
                            ? field.options
                            : String(field.options || '')
                                .split(',')
                                .map((opt) => opt.trim())
                                .filter(Boolean);
                        return (
                            <SelectControl
                                key={field.key}
                                label={field.label}
                                value={inputs[field.key] || ''}
                                options={options.map((opt) => ({ label: opt, value: opt }))}
                                onChange={(value) => handleInputChange(field.key, value)}
                            />
                        );
                    }
                    return (
                        <TextControl
                            key={field.key}
                            label={field.label}
                            value={inputs[field.key] || ''}
                            onChange={(value) => handleInputChange(field.key, value)}
                            type={field.type === 'number' ? 'number' : field.type === 'url' ? 'url' : field.type === 'password' ? 'password' : 'text'}
                            help={field.placeholder}
                        />
                    );
                })}

                <div style={{ display: 'flex', gap: '8px', marginTop: '12px' }}>
                    <Button variant="primary" isBusy={isBusy} onClick={runAgent}>
                        {__('Generate', 'ai-gateway')}
                    </Button>
                    <Button variant="secondary" onClick={injectOutput}>
                        {__('Insert', 'ai-gateway')}
                    </Button>
                    <Button variant="tertiary" onClick={resetInputs}>
                        {__('Reset', 'ai-gateway')}
                    </Button>
                </div>

                <TextareaControl
                    label={__('Result', 'ai-gateway')}
                    value={output}
                    onChange={setOutput}
                    help={meta}
                />
            </PanelBody>

            {hasTool('smart_edit') && (
                <PanelBody title={__('Smart Edit', 'ai-gateway')} initialOpen={false}>
                {smartError && (
                    <Notice status="error" isDismissible={false}>
                        {smartError}
                    </Notice>
                )}
                <SelectControl
                    label={__('Target block', 'ai-gateway')}
                    value={targetBlockId}
                    options={blockOptions}
                    onChange={(value) => setTargetBlockId(value)}
                />
                <TextareaControl
                    label={__('Block text', 'ai-gateway')}
                    value={smartContent}
                    onChange={setSmartContent}
                />
                <TextControl
                    label={__('Text color (hex)', 'ai-gateway')}
                    value={smartTextColor}
                    onChange={setSmartTextColor}
                />
                <TextControl
                    label={__('Background color (hex)', 'ai-gateway')}
                    value={smartBgColor}
                    onChange={setSmartBgColor}
                />
                <Button variant="primary" onClick={applySmartEdit}>
                    {__('Apply changes', 'ai-gateway')}
                </Button>
                </PanelBody>
            )}

            {hasTool('outline_sections') && (
                <PanelBody title={__('Outline to Sections', 'ai-gateway')} initialOpen={false}>
                <TextareaControl
                    label={__('Outline (one per line)', 'ai-gateway')}
                    value={outlineText}
                    onChange={setOutlineText}
                />
                <Button variant="primary" onClick={outlineToSections}>
                    {__('Insert sections', 'ai-gateway')}
                </Button>
                </PanelBody>
            )}

            {hasTool('page_template') && (
                <PanelBody title={__('Page Template', 'ai-gateway')} initialOpen={false}>
                <SelectControl
                    label={__('Template', 'ai-gateway')}
                    value={templateType}
                    options={[
                        { label: 'Landing', value: 'landing' },
                        { label: 'Blog', value: 'blog' },
                        { label: 'Service', value: 'service' },
                    ]}
                    onChange={setTemplateType}
                />
                <Button variant="primary" onClick={insertTemplate}>
                    {__('Insert template', 'ai-gateway')}
                </Button>
                </PanelBody>
            )}

            {hasTool('faq_builder') && (
                <PanelBody title={__('FAQ Builder', 'ai-gateway')} initialOpen={false}>
                <TextareaControl
                    label={__('FAQ input (Q:/A:)', 'ai-gateway')}
                    value={faqText}
                    onChange={setFaqText}
                />
                <Button variant="primary" onClick={insertFaq}>
                    {__('Insert FAQ', 'ai-gateway')}
                </Button>
                </PanelBody>
            )}

            {hasTool('cta_builder') && (
                <PanelBody title={__('CTA Builder', 'ai-gateway')} initialOpen={false}>
                <TextControl label={__('Title', 'ai-gateway')} value={ctaTitle} onChange={setCtaTitle} />
                <TextareaControl label={__('Body', 'ai-gateway')} value={ctaBody} onChange={setCtaBody} />
                <TextControl
                    label={__('Button text', 'ai-gateway')}
                    value={ctaButtonText}
                    onChange={setCtaButtonText}
                />
                <TextControl
                    label={__('Button URL', 'ai-gateway')}
                    value={ctaButtonUrl}
                    onChange={setCtaButtonUrl}
                />
                <Button variant="primary" onClick={insertCta}>
                    {__('Insert CTA', 'ai-gateway')}
                </Button>
                </PanelBody>
            )}

            {hasTool('quick_palette') && (
                <PanelBody title={__('Quick Palette', 'ai-gateway')} initialOpen={false}>
                <TextControl
                    label={__('Text color (hex)', 'ai-gateway')}
                    value={paletteTextColor}
                    onChange={setPaletteTextColor}
                />
                <TextControl
                    label={__('Background color (hex)', 'ai-gateway')}
                    value={paletteBgColor}
                    onChange={setPaletteBgColor}
                />
                <ToggleControl
                    label={__('Apply to all blocks', 'ai-gateway')}
                    checked={paletteAllBlocks}
                    onChange={setPaletteAllBlocks}
                />
                <Button variant="primary" onClick={applyPalette}>
                    {__('Apply palette', 'ai-gateway')}
                </Button>
                </PanelBody>
            )}

            {hasTool('media_insert') && (
                <PanelBody title={__('Media Smart Insert', 'ai-gateway')} initialOpen={false}>
                <MediaUploadCheck>
                    <MediaUpload
                        onSelect={insertImage}
                        allowedTypes={['image']}
                        render={({ open }) => (
                            <Button variant="secondary" onClick={open}>
                                {__('Select image', 'ai-gateway')}
                            </Button>
                        )}
                    />
                </MediaUploadCheck>
                </PanelBody>
            )}

            {hasTool('hero_section') && (
                <PanelBody title={__('Hero Section', 'ai-gateway')} initialOpen={false}>
                <TextControl label={__('Title', 'ai-gateway')} value={heroTitle} onChange={setHeroTitle} />
                <TextareaControl label={__('Subtitle', 'ai-gateway')} value={heroSubtitle} onChange={setHeroSubtitle} />
                <TextControl
                    label={__('Button text', 'ai-gateway')}
                    value={heroButtonText}
                    onChange={setHeroButtonText}
                />
                <TextControl
                    label={__('Button URL', 'ai-gateway')}
                    value={heroButtonUrl}
                    onChange={setHeroButtonUrl}
                />
                <MediaUploadCheck>
                    <MediaUpload
                        onSelect={setHeroImage}
                        allowedTypes={['image']}
                        render={({ open }) => (
                            <Button variant="secondary" onClick={open}>
                                {heroImage?.url ? __('Change image', 'ai-gateway') : __('Select image', 'ai-gateway')}
                            </Button>
                        )}
                    />
                </MediaUploadCheck>
                <Button variant="primary" onClick={insertHero}>
                    {__('Insert hero', 'ai-gateway')}
                </Button>
                </PanelBody>
            )}
        </PluginSidebar>
    );
}

registerPlugin('ai-gateway-plugin', {
    render: AIGatewaySidebar,
});
