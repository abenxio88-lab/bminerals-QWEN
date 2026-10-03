/**
 * Balochistan Minerals - AI Chatbot Knowledge Base & NLP Engine
 * Concise, conversational B2B executive mineral responses and trade intelligence.
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
    image: 'images/barite-card-480.avif',
    badge: 'API 13A SG 4.20+',
    origin: 'Khuzdar, Balochistan',
    dialogue: 'We supply export-ready **API 13A Barite (SG 4.20+)** directly from our **Khuzdar** deposits, available in crude lumps and 200-mesh milled powder. High-purity 92–98% BaSO₄ chemical grades are also available.\n\nWould you like current FOB Karachi pricing or a pre-shipment assay certificate?',
    url: 'product-industrial.html#barite',
    urlLabel: 'View Barite API 13A Specifications'
  },
  chromite: {
    id: 'chromite',
    name: 'Chromite (FeCr2O4)',
    title: 'Chrome Ore & Concentrate',
    image: 'images/chromite-new-480.avif',
    badge: '42% - 52% Cr2O3',
    origin: 'Muslim Bagh Belt',
    dialogue: 'We source high-grade **Chromite** from our active **Muslim Bagh** operations, offering **42%–52% Cr₂O₃** metallurgical lumps and concentrates with certified Cr:Fe ratios.\n\nWould you like to request current lot availability or receive an official quotation?',
    url: 'product-metallic.html#chromite',
    urlLabel: 'View Metallurgical Chromite Specifications'
  },
  copper: {
    id: 'copper',
    name: 'Copper Ore (Cu)',
    title: 'Copper Ore & DSO',
    image: 'images/copper-new-480.avif',
    badge: '2% - 10% Cu DSO',
    origin: 'Chagai Metallogenic Belt',
    dialogue: 'Our copper operations in the **Chagai metallogenic belt** supply **2%–10% Cu Direct Shipping Ore (DSO)** and lump ore for international smelters, verified with independent SGS assay certificates.\n\nWould you like to review recent lot assays or discuss target tonnage?',
    url: 'product-metallic.html#copper',
    urlLabel: 'View Chagai Copper Ore Specifications'
  },
  ironOre: {
    id: 'iron-ore',
    name: 'Iron Ore (Fe)',
    title: 'Magnetite & Hematite',
    image: 'images/iron-ore-new-480.avif',
    badge: 'Fe 40% - 62%',
    origin: 'Balochistan Deposits',
    dialogue: 'We export **Iron Ore (Fe 40%–62%)** in direct reduction lumps and beneficiated magnetite concentrate with low phosphorus and sulfur, shipped in bulk vessels or containers from Karachi ports.\n\nWould you like current specification sheets or port delivery terms?',
    url: 'product-metallic.html#iron-ore',
    urlLabel: 'View Iron Ore Magnetite/Hematite Specs'
  },
  fluorite: {
    id: 'fluorite',
    name: 'Fluorite (CaF2)',
    title: 'Fluorspar',
    image: 'images/fluorite-480.avif',
    badge: '50% - 90%+ CaF2',
    origin: 'Kalat Belt',
    dialogue: 'We supply metallurgical-grade **Fluorspar (50%–90%+ CaF₂)** from Kalat for steelmaking flux and industrial applications, available in sized lumps and gravel.\n\nWould you like to check current lot availability or request a quotation?',
    url: 'product-industrial.html#fluorite',
    urlLabel: 'View Fluorspar (CaF₂) Specifications'
  },
  antimony: {
    id: 'antimony',
    name: 'Antimony (Sb)',
    title: 'Stibnite Ore',
    image: 'images/antimony-480.avif',
    badge: 'Sb 4% - 58%',
    origin: 'Qila Abdullah',
    dialogue: 'We provide high-density **Stibnite / Antimony Ore (4%–58% Sb)** from Qila Abdullah, prepared in crystalline lumps and gravity-concentrated lots for industrial and battery alloys.\n\nWould you like assay details or export delivery schedules?',
    url: 'product-metallic.html#antimony',
    urlLabel: 'View Antimony Stibnite Specifications'
  },
  gypsum: {
    id: 'gypsum',
    name: 'Gypsum (CaSO4·2H2O)',
    title: 'Natural Gypsum',
    image: 'images/gypsum-480.avif',
    badge: '90% - 95% Purity',
    origin: 'Balochistan Beds',
    dialogue: 'We supply natural **90%–95% high-purity Gypsum** for Portland cement plants and construction plaster, shipped in break-bulk charter from Karachi ports.\n\nWould you like to discuss vessel chartering or lot specifications?',
    url: 'product-industrial.html#gypsum',
    urlLabel: 'View Industrial Gypsum Specifications'
  },
  magnesite: {
    id: 'magnesite',
    name: 'Magnesite (MgCO3)',
    title: 'Raw & Calcined Magnesite',
    image: 'images/magnesite-480.avif',
    badge: 'Raw MgO 42-47%',
    origin: 'Muslim Bagh / Khuzdar',
    dialogue: 'Our ophiolite deposits yield **42%–47% raw MgO Magnesite** and calcined options for refractory brick linings and agricultural compounds.\n\nWould you like technical specifications or pricing details?',
    url: 'product-industrial.html#magnesite',
    urlLabel: 'View Magnesite Chemical Specifications'
  },
  phosphate: {
    id: 'phosphate-rock',
    name: 'Phosphate Rock',
    title: 'Phosphate Feedstock',
    image: 'images/phosphate-rock-480.avif',
    badge: 'P2O5 Assay Lot',
    origin: 'Balochistan Basins',
    dialogue: 'We supply commercial rock phosphate feedstock (**22%–30% P₂O₅**) for single superphosphate (SSP) manufacturing and direct fertilizer blending.\n\nWould you like to discuss tonnage requirements or shipment terms?',
    url: 'product-industrial.html#phosphate-rock',
    urlLabel: 'View Rock Phosphate Feedstock Specs'
  },
  bauxite: {
    id: 'bauxite',
    name: 'Bauxite Ore',
    title: 'Alumina Bauxite',
    image: 'images/bauxite-480.avif',
    badge: 'Al2O3 45% - 62%',
    origin: 'Balochistan Belts',
    dialogue: 'We provide **45%–62% Al₂O₃ Bauxite** with controlled silica for refractory cement, abrasives manufacturing, and metallurgical refining.\n\nWould you like to request assay data or FOB pricing?',
    url: 'product-industrial.html#bauxite',
    urlLabel: 'View Alumina Bauxite Specifications'
  },
  stones: {
    id: 'stones',
    name: 'Marble & Stone Slabs',
    title: 'Dimensional Stones',
    image: 'images/silver-steam-white-marble-1-480.avif',
    badge: 'Blocks & Slabs',
    origin: 'Balochistan Quarries',
    dialogue: 'We quarry and supply premium **Persian Silk Tundra Grey**, **Pietra Grey**, and white marble blocks and gangsaw slabs with seaworthy A-frame packaging.\n\nWould you like to view current quarry block inventory or slab photos?',
    url: 'product-stones.html',
    urlLabel: 'View Marble Slabs & Blocks Gallery'
  },
  coal: {
    id: 'coal',
    name: 'Industrial Coal',
    title: 'Sub-Bituminous Coal',
    image: 'images/coal-480.avif',
    badge: '5,000 - 6,800 kcal',
    origin: 'Sorange-Degari & Mach',
    dialogue: 'We supply **5,000–6,800 kcal/kg industrial steam coal** from the Sorange-Degari and Mach fields for cement clinker kilns and industrial boilers.\n\nWould you like delivery terms for Karachi stockpile or rail rakes?',
    url: 'product-energy.html',
    urlLabel: 'View Industrial Steam Coal Specs'
  }
};

export const FREQUENT_TOPICS = [
  {
    topic: 'rfq_pricing',
    keywords: ['price', 'pricing', 'quote', 'cost', 'rfq', 'rate', 'quotation', 'inquiry', 'buy', 'order', 'purchase', 'per ton', 'how much'],
    title: 'Commercial Quotations & RFQ Desk',
    image: null,
    badge: 'Commercial Terms',
    url: 'contact.html#inquiry-form',
    urlLabel: 'Open Direct Commercial Inquiry Desk',
    answer: `Commercial pricing is determined by fresh lot assay, tonnage, and chosen Incoterm (**FOB Karachi**, **CFR**, or **CIF** destination port).

• **Payment:** L/C at sight from prime international bank, or T/T with advance deposit.
• **Minimum Order:** 1 FCL (~27 MT) for container trial orders; 5,000 - 45,000 MT for break-bulk charter.
• **Inspection:** Independent SGS / Alfred H Knight assay certification prior to vessel loading.

*Please select your target tonnage below to generate a direct inquiry for our commercial desk.*`
  },
  {
    topic: 'logistics_shipping',
    keywords: ['logistics', 'shipping', 'port', 'karachi', 'qasim', 'gwadar', 'vessel', 'container', 'fob', 'cif', 'cfr', 'delivery', 'transport', 'incoterm', 'freight'],
    title: 'Mine-to-Port Logistics & Gateways',
    image: 'images/baochistan-mineral-resources-480.avif',
    badge: 'Export Logistics',
    url: 'logistics.html',
    urlLabel: 'Explore Mine-to-Port Logistics & Ports',
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
    url: 'sustainability.html#governance',
    urlLabel: 'Review Quality Control & Assay Protocols',
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
    url: 'contact.html#inquiry-form',
    urlLabel: 'Request Testing Samples via Courier',
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
    url: 'our-mines.html',
    urlLabel: 'View Balochistan Mining Corridors',
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
    url: 'contact.html',
    urlLabel: 'View Commercial Office & Contact Details',
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
 * Analyzes natural language input and builds concise, human-level trade responses.
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
      if (hasRfqIntent) {
        return {
          type: 'rfq_mineral',
          mineral: mineral,
          title: mineral.name,
          image: mineral.image,
          badge: mineral.badge,
          origin: mineral.origin,
          text: `To prepare an official commercial quotation for **${mineral.name}**, please verify your required tonnage and destination port below:`,
          url: mineral.url,
          urlLabel: mineral.urlLabel || `View Official ${mineral.name} Specifications`,
          showRfqCard: true,
          rfqData: {
            mineralName: mineral.title,
            defaultGrade: mineral.badge,
            url: mineral.url
          },
          chips: ['Logistics & Ports', 'Assay Certification', 'Talk to Sales Specialist']
        };
      }

      // Natural, conversational B2B response (no bullet-point dump!)
      return {
        type: 'mineral_detail',
        mineral: mineral,
        title: mineral.name,
        image: mineral.image,
        badge: mineral.badge,
        origin: mineral.origin,
        text: mineral.dialogue,
        url: mineral.url,
        urlLabel: mineral.urlLabel || `View Official ${mineral.name} Specifications`,
        showRfqCard: false,
        chips: [`Request ${mineral.title} Quote`, 'Logistics & Ports', 'Assay Quality', 'All Minerals']
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
        url: topic.url,
        urlLabel: topic.urlLabel,
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
    url: 'products.html',
    urlLabel: 'Browse Complete Mineral Catalog',
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
