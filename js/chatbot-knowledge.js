/**
 * Balochistan Minerals - AI Chatbot Knowledge Base & NLP Engine
 * High-impact, concise B2B executive mineral specifications and logistics intelligence.
 */

export const COMPANY_INFO = {
  name: 'Balochistan Minerals (Pvt) Ltd',
  registration: 'SECP Registered (Private Limited)',
  email: 'sales@balochistanminerals.com',
  phone: '+92 334 8888104',
  whatsappNumber: '923348888104',
  headOffice: 'Karachi, Sindh, Pakistan (Commercial & Export Logistics Desk)',
  regionalOffice: 'Quetta, Balochistan, Pakistan (Mining Operations Hub)',
  exportPorts: ['Port of Karachi (KPT)', 'Port Muhammad Bin Qasim (PQA)', 'Gwadar Deep Sea Port'],
  incoterms: ['FOB Karachi / Qasim', 'CFR Destination Port', 'CIF Destination Port'],
  inspectionAgencies: ['SGS Pakistan', 'Alfred H Knight (AHK)', 'Inspectorate']
};

export const MINERAL_DATABASE = {
  barite: {
    id: 'barite',
    name: 'Barite (BaSO4)',
    title: 'Barite / Baryte',
    formula: 'Barium Sulfate',
    category: 'Industrial Minerals',
    image: 'images/barite-card-480.avif',
    badge: 'API 13A SG 4.20+',
    origin: 'Khuzdar, Balochistan',
    summary: 'High-density weighting agent for oil & gas drilling muds (API Spec 13A certified) and high-purity chemical filler applications.',
    specs: [
      { label: 'Drilling Grade', value: 'SG 4.20+ g/cm³ (API 13A)' },
      { label: 'Chemical Grade', value: '92% - 98% BaSO4' },
      { label: 'Forms Available', value: 'Crude Lumps & 200/325 Mesh' },
      { label: 'Export Packing', value: '1.5 MT Jumbo Bags / Bulk' }
    ],
    url: 'product-industrial.html#barite'
  },
  chromite: {
    id: 'chromite',
    name: 'Chromite (FeCr2O4)',
    title: 'Chrome Ore & Concentrate',
    formula: 'Chromite Ore',
    category: 'Metallic Minerals',
    image: 'images/chromite-new-480.avif',
    badge: '42% - 52% Cr2O3',
    origin: 'Muslim Bagh Belt',
    summary: 'High-grade metallurgical chrome lumps and concentrates for ferrochrome production, stainless steel, and refractory foundries.',
    specs: [
      { label: 'Metallurgical Lumps', value: '42% - 52% Cr2O3 (Cr:Fe 2.6:1+)' },
      { label: 'Foundry / Medium', value: '32% - 40% Cr2O3' },
      { label: 'Forms Available', value: 'Lumps (10-50mm) & Concentrate' },
      { label: 'Export Packing', value: '20ft FCL (~27 MT) / Jumbo Bags' }
    ],
    url: 'product-metallic.html#chromite'
  },
  copper: {
    id: 'copper',
    name: 'Copper Ore (Cu)',
    title: 'Copper Ore & DSO',
    formula: 'Direct Shipping Ore',
    category: 'Metallic Minerals',
    image: 'images/copper-new-480.avif',
    badge: '2% - 10% Cu DSO',
    origin: 'Chagai Metallogenic Belt',
    summary: 'Direct Shipping Ore (DSO) and lump ore extracted from the Tethyan belt in Chagai for international smelters and trading programs.',
    specs: [
      { label: 'Direct Shipping Ore', value: '3% - 10%+ Cu Lumps' },
      { label: 'Medium Grade', value: '2% - 5% Cu Sourced Lots' },
      { label: 'Forms Available', value: 'Run-of-mine & Sized Lumps' },
      { label: 'Export Packing', value: 'Containerized Jumbo Bags / Bulk' }
    ],
    url: 'product-metallic.html#copper'
  },
  ironOre: {
    id: 'iron-ore',
    name: 'Iron Ore (Fe)',
    title: 'Magnetite & Hematite',
    formula: 'Fe2O3 / Fe3O4',
    category: 'Metallic Minerals',
    image: 'images/iron-ore-new-480.avif',
    badge: 'Fe 40% - 62%',
    origin: 'Balochistan Deposits',
    summary: 'Direct reduction lumps and beneficiated magnetite concentrate with controlled phosphorus and sulfur for steelmaking and cement works.',
    specs: [
      { label: 'Steel Mill Lumps', value: '50% - 58% Fe' },
      { label: 'Concentrate Grade', value: '56% - 62%+ Fe (Low Gangue)' },
      { label: 'Forms Available', value: 'Lumps (10-40mm) & Fines' },
      { label: 'Export Packing', value: 'Break-Bulk Vessel & Containers' }
    ],
    url: 'product-metallic.html#iron-ore'
  },
  fluorite: {
    id: 'fluorite',
    name: 'Fluorite (CaF2)',
    title: 'Fluorspar',
    formula: 'Calcium Fluoride',
    category: 'Industrial Minerals',
    image: 'images/fluorite-480.avif',
    badge: '50% - 90%+ CaF2',
    origin: 'Kalat Belt',
    summary: 'Essential fluxing material for electric arc steelmaking, ceramic glazes, glass manufacturing, and acid-grade chemical feedstock.',
    specs: [
      { label: 'Metallurgical Metspar', value: '60% - 85% CaF2' },
      { label: 'High Grade / Acid', value: '85% - 92%+ CaF2' },
      { label: 'Forms Available', value: 'Lumps (10-60mm) & Gravel' },
      { label: 'Export Packing', value: '1.0 MT Jumbo Bags' }
    ],
    url: 'product-industrial.html#fluorite'
  },
  antimony: {
    id: 'antimony',
    name: 'Antimony (Sb)',
    title: 'Stibnite Ore',
    formula: 'Sb2S3',
    category: 'Metallic Minerals',
    image: 'images/antimony-480.avif',
    badge: 'Sb 4% - 58%',
    origin: 'Qila Abdullah',
    summary: 'High-density antimony sulfide ore utilized for flame retardants, solar glass clarification, lead-acid batteries, and strategic alloys.',
    specs: [
      { label: 'High Grade Lumps', value: '25% - 58% Sb' },
      { label: 'Concentrate', value: '20% - 60% Sb Flotation' },
      { label: 'Forms Available', value: 'Hand-sorted Lumps & Fines' },
      { label: 'Export Packing', value: 'Sealed Jumbo Bags in 20ft FCL' }
    ],
    url: 'product-metallic.html#antimony'
  },
  gypsum: {
    id: 'gypsum',
    name: 'Gypsum (CaSO4·2H2O)',
    title: 'Natural Gypsum',
    formula: 'Calcium Sulfate Dihydrate',
    category: 'Industrial Minerals',
    image: 'images/gypsum-480.avif',
    badge: '90% - 95% Purity',
    origin: 'Balochistan Beds',
    summary: 'Natural high-purity gypsum supplied as retarder for Portland cement plants, drywall board manufacturing, and agricultural soil conditioning.',
    specs: [
      { label: 'Purity Level', value: '90% - 95% CaSO4·2H2O' },
      { label: 'Moisture', value: '< 1.5% Free Moisture' },
      { label: 'Forms Available', value: 'Lumps (0-150mm) & Crushed' },
      { label: 'Export Packing', value: 'Break-Bulk Vessel Charter' }
    ],
    url: 'product-industrial.html#gypsum'
  },
  magnesite: {
    id: 'magnesite',
    name: 'Magnesite (MgCO3)',
    title: 'Raw & Calcined Magnesite',
    formula: 'Magnesium Carbonate',
    category: 'Industrial Minerals',
    image: 'images/magnesite-480.avif',
    badge: 'Raw MgO 42-47%',
    origin: 'Muslim Bagh / Khuzdar',
    summary: 'Refractory ore for steel converter linings, dead-burned magnesia (DBM), and caustic calcined compounds for agriculture.',
    specs: [
      { label: 'Raw Ore Grade', value: '42% - 47% MgO (88-96% MgCO3)' },
      { label: 'Calcined Options', value: '85% - 90% Reactive MgO' },
      { label: 'Forms Available', value: 'Dense Lumps & Briquettes' },
      { label: 'Export Packing', value: '1.0 MT Jumbo Bags' }
    ],
    url: 'product-industrial.html#magnesite'
  },
  phosphate: {
    id: 'phosphate-rock',
    name: 'Phosphate Rock',
    title: 'Phosphate Feedstock',
    formula: 'P2O5 Feedstock',
    category: 'Industrial Minerals',
    image: 'images/phosphate-rock-480.avif',
    badge: 'P2O5 Assay Lot',
    origin: 'Balochistan Basins',
    summary: 'Commercial rock phosphate feedstock for single superphosphate (SSP), phosphoric acid, and direct soil fertilizer blending.',
    specs: [
      { label: 'Assay Base', value: '22% - 30% P2O5 per Lot' },
      { label: 'Forms Available', value: 'Crushed Rock & Screened Feed' },
      { label: 'Export Packing', value: 'Bulk Vessel & Containers' }
    ],
    url: 'product-industrial.html#phosphate-rock'
  },
  bauxite: {
    id: 'bauxite',
    name: 'Bauxite Ore',
    title: 'Alumina Bauxite',
    formula: 'Al2O3·2H2O',
    category: 'Industrial Minerals',
    image: 'images/bauxite-480.avif',
    badge: 'Al2O3 45% - 62%',
    origin: 'Balochistan Belts',
    summary: 'Alumina-rich ore suited for refractory cement, abrasives manufacturing, metallurgical refining, and steel deoxidation.',
    specs: [
      { label: 'Alumina Content', value: '45% - 62% Al2O3' },
      { label: 'Forms Available', value: 'Crude Lumps & Screened Ore' },
      { label: 'Export Packing', value: 'Bulk Containerized / Jumbo Bags' }
    ],
    url: 'product-industrial.html#bauxite'
  },
  stones: {
    id: 'stones',
    name: 'Marble & Stone Slabs',
    title: 'Dimensional Stones',
    formula: 'Persian Silk & Pietra Grey',
    category: 'Dimensional Stones',
    image: 'images/silver-steam-white-marble-1-480.avif',
    badge: 'Blocks & Slabs',
    origin: 'Balochistan Quarries',
    summary: 'Quarried dimensional stones including Persian Silk Tundra Grey, Pietra Grey, and Onyx for luxury architectural projects and slab export.',
    specs: [
      { label: 'Materials', value: 'Persian Silk, Pietra Grey, White Onyx' },
      { label: 'Forms Available', value: 'Gangsaw Slabs (20/30mm) & Blocks' },
      { label: 'Export Packing', value: 'Seaworthy Wooden A-Frames' }
    ],
    url: 'product-stones.html'
  },
  coal: {
    id: 'coal',
    name: 'Industrial Coal',
    title: 'Sub-Bituminous Coal',
    formula: 'Carbonaceous Energy',
    category: 'Energy Minerals',
    image: 'images/coal-480.avif',
    badge: '5,000 - 6,800 kcal',
    origin: 'Sorange-Degari & Mach',
    summary: 'High calorific industrial steam coal supplying cement clinker kilns, brick kilns, and industrial boilers.',
    specs: [
      { label: 'Calorific Value', value: '5,000 - 6,800 kcal/kg GCV' },
      { label: 'Forms Available', value: 'ROM Lumps (0-200mm) & Screened' },
      { label: 'Delivery', value: 'Karachi Port Stockpile & Bulk Rakes' }
    ],
    url: 'product-energy.html'
  }
};

export const FREQUENT_TOPICS = [
  {
    topic: 'rfq_pricing',
    keywords: ['price', 'pricing', 'quote', 'cost', 'rfq', 'rate', 'quotation', 'inquiry', 'buy', 'order', 'purchase', 'per ton', 'how much'],
    title: 'Commercial Quotations & RFQ Desk',
    image: null,
    badge: 'Commercial Terms',
    answer: `Commercial pricing is determined by fresh lot assay, tonnage, and chosen Incoterm (**FOB Karachi**, **CFR**, or **CIF** destination port).

• **Payment:** L/C at sight from prime international bank, or T/T with advance deposit.
• **Minimum Order:** 1 FCL (~27 MT) for container trial orders; 5,000 - 45,000 MT for break-bulk charter.
• **Inspection:** Independent SGS / Alfred H Knight assay certification prior to vessel loading.

*Use the quick inquiry card below to submit your specifications directly to our commercial desk.*`
  },
  {
    topic: 'logistics_shipping',
    keywords: ['logistics', 'shipping', 'port', 'karachi', 'qasim', 'gwadar', 'vessel', 'container', 'fob', 'cif', 'cfr', 'delivery', 'transport', 'incoterm', 'freight'],
    title: 'Mine-to-Port Logistics & Gateways',
    image: 'images/baochistan-mineral-resources-480.avif',
    badge: 'Export Logistics',
    answer: `We operate integrated road, rail, and port stockyards directly serving Pakistan export hubs:

• **Gateways:** Port of Karachi (KPT), Port Muhammad Bin Qasim (PQA), and Gwadar Deep Sea Port.
• **Container Shipments:** 20ft dry containers with 1.0 MT - 1.5 MT heavy-duty jumbo bags (~26-28 MT/FCL).
• **Break-Bulk Vessels:** Handymax / Supramax chartering (15,000 - 45,000 MT).
• **Incoterms:** FOB Karachi, CFR, CIF worldwide ports (China, GCC, Far East, Europe).`
  },
  {
    topic: 'quality_assay',
    keywords: ['assay', 'coa', 'quality', 'sgs', 'inspection', 'lab', 'certificate', 'analysis', 'purity', 'ahk', 'testing', 'report'],
    title: 'Assay Quality & Certification',
    image: null,
    badge: 'Certified Assay',
    answer: `Every mineral lot is sampled and verified by accredited third-party surveyors prior to export:

• **Accredited Inspection:** **SGS Pakistan**, **Alfred H Knight (AHK)**, or buyer-nominated surveyors.
• **Full Documentation:** Certificate of Analysis (COA), Certificate of Origin, Weight Slip, and clean Bill of Lading.
• **Witnessed Sampling:** Buyers are welcome to inspect stockpile lots at mine gate or Karachi port yards.`
  },
  {
    topic: 'samples',
    keywords: ['sample', 'specimen', 'trial', 'testing sample', 'courier', 'dhl'],
    title: 'Laboratory Testing Specimens',
    image: null,
    badge: 'Courier Dispatch',
    answer: `We provide genuine, representative mineral specimens (1 kg - 5 kg) for metallurgical testing and furnace evaluation:

• **Dispatch:** Shipped via DHL / FedEx with preliminary assay slip.
• **Requirements:** Company name, target chemical grade, and destination courier address.
• **Trial Orders:** 1-2 container trial shipments available for kiln testing.`
  },
  {
    topic: 'mines_locations',
    keywords: ['mine', 'mines', 'origin', 'where', 'location', 'muslim bagh', 'khuzdar', 'chagai', 'quetta', 'karachi', 'balochistan'],
    title: 'Mining Belts & Operations',
    image: 'images/baochistan-mineral-resources-480.avif',
    badge: 'Mining Operations',
    answer: `Our extraction networks span Balochistan’s richest geological belts:

• **Muslim Bagh:** High-grade metallurgical chromite lumps & concentrate.
• **Khuzdar District:** API 13A high-gravity barite (SG 4.20+ g/cm³).
• **Chagai Arc:** Direct shipping copper ore (2%-10% Cu).
• **Qila Abdullah:** Crystalline antimony (Stibnite) deposits.
• **Quetta Hub:** Mine logistics & regional assay dispatch.`
  },
  {
    topic: 'contact_office',
    keywords: ['contact', 'office', 'phone', 'email', 'address', 'whatsapp', 'reach', 'talk', 'human', 'representative', 'sales'],
    title: 'Direct Commercial Desk',
    image: null,
    badge: 'Contact Desk',
    answer: `Connect directly with our commercial trading team:

• **Email:** [sales@balochistanminerals.com](mailto:sales@balochistanminerals.com)
• **Phone / WhatsApp:** [+92 334 8888104](https://wa.me/923348888104)
• **Karachi Desk:** Export documentation & vessel logistics.
• **Quetta Hub:** Mining site operations & stockyard inspection.
• **Response Time:** Trade inquiries reviewed within 2 to 4 hours.`
  }
];

/**
 * Intelligent Intent Classifier & Matcher
 * Analyzes natural language input and builds concise, executive responses.
 */
export function findBestAnswer(query) {
  const clean = (query || '').toLowerCase().trim();
  if (!clean) {
    return {
      type: 'greeting',
      title: 'How can I assist your mineral sourcing today?',
      text: 'Sourcing export-grade minerals from Pakistan? Select a commodity below or type your target specifications.',
      chips: ['Barite 4.2+ SG', 'Chromite 42-52%', 'Chagai Copper', 'Logistics & Ports', 'Request Quote']
    };
  }

  // 1. Direct RFQ / Quote Intent
  const rfqTerms = ['quote', 'rfq', 'price', 'pricing', 'cost', 'buy', 'order', 'quotation', 'rate', 'how much', 'tonnage'];
  const hasRfqIntent = rfqTerms.some(term => clean.includes(term));

  // Match specific mineral
  for (const [key, mineral] of Object.entries(MINERAL_DATABASE)) {
    const aliases = [mineral.id, mineral.name.toLowerCase(), key.toLowerCase()];
    if (key === 'ironOre') aliases.push('iron', 'iron ore', 'magnetite', 'hematite');
    if (key === 'stones') aliases.push('marble', 'onyx', 'persian silk', 'pietra grey', 'granite', 'stone');
    if (key === 'fluorite') aliases.push('fluorspar');
    if (key === 'chromite') aliases.push('chrome', 'cr2o3');
    if (key === 'copper') aliases.push('cu', 'malachite', 'chalcopyrite');
    if (key === 'barite') aliases.push('baryte', 'baso4', 'drilling mud');

    const matchedMineral = aliases.some(alias => clean.includes(alias));

    if (matchedMineral) {
      // Build concise spec bullet points
      const specBullets = mineral.specs
        .map(s => `• **${s.label}:** ${s.value}`)
        .join('\n');

      return {
        type: 'mineral_detail',
        mineral: mineral,
        title: mineral.name,
        image: mineral.image,
        badge: mineral.badge,
        origin: mineral.origin,
        text: `${mineral.summary}

${specBullets}

[View Full Technical Details & Certificates →](${mineral.url})`,
        url: mineral.url,
        showRfqCard: true,
        rfqData: {
          mineralName: mineral.title,
          defaultGrade: mineral.badge,
          url: mineral.url
        },
        chips: [`Quote for ${mineral.title}`, 'Logistics & Ports', 'Assay Certification', 'Contact Desk']
      };
    }
  }

  // 2. Check Frequent Topics
  for (const topic of FREQUENT_TOPICS) {
    const matchCount = topic.keywords.filter(kw => clean.includes(kw)).length;
    if (matchCount > 0) {
      return {
        type: 'topic',
        topicId: topic.topic,
        title: topic.title,
        text: topic.answer,
        image: topic.image || null,
        badge: topic.badge || null,
        showRfqCard: topic.topic === 'rfq_pricing',
        chips: topic.topic === 'rfq_pricing'
          ? ['Barite 4.2+ SG', 'Chromite 42-52%', 'Chagai Copper', 'Contact Desk']
          : ['Request Quote', 'Logistics & Ports', 'Assay Quality', 'All Minerals']
      };
    }
  }

  // 3. Fallback Smart Response
  return {
    type: 'fallback',
    title: 'Balochistan Minerals AI Specialist',
    text: `I specialize in Pakistan mineral sourcing, certified assay specifications, and export logistics:

• **Key Minerals:** Barite (4.2+ SG), Chromite (42-52%), Copper (Chagai DSO), Iron Ore (40-62%), Fluorite, and Marble.
• **Logistics:** Containerized & break-bulk vessel export via Karachi & Qasim ports.
• **Quality:** Pre-shipment assay reports by SGS & Alfred H Knight.`,
    chips: [
      'Barite 4.2+ SG',
      'Chromite 42-52%',
      'Chagai Copper',
      'Logistics & Ports',
      'Request Quote'
    ]
  };
}

/**
 * Pre-filled WhatsApp RFQ URL
 */
export function buildWhatsAppRfqUrl({ mineral, quantity, destinationPort, contactName, company }) {
  const text = `*BALOCHISTAN MINERALS - COMMERCIAL INQUIRY (RFQ)*
--------------------------------------
• *Mineral:* ${mineral || 'Mineral Supply'}
• *Quantity:* ${quantity || 'Trial / FCL Order'}
• *Destination Port:* ${destinationPort || 'FOB Karachi / CIF'}
--------------------------------------
Sent via AI Portal. Please advise availability and current pricing.`;

  return `https://wa.me/${COMPANY_INFO.whatsappNumber}?text=${encodeURIComponent(text)}`;
}

/**
 * Pre-filled Email RFQ URL
 */
export function buildEmailRfqUrl({ mineral, quantity, destinationPort, contactName, company }) {
  const subject = `[RFQ] Commercial Inquiry for ${mineral || 'Mineral Supply'}`;
  const body = `Dear Balochistan Minerals Commercial Team,

Please provide an official quotation and specification sheet for:

- Commodity: ${mineral || 'Export Minerals'}
- Target Quantity: ${quantity || 'FCL / Bulk'}
- Destination Port: ${destinationPort || 'FOB Karachi / CIF'}

Please advise current assay data (COA) and availability.

Kind regards,
Commercial Buyer`;

  return `mailto:${COMPANY_INFO.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}
