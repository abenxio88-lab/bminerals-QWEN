/**
 * Balochistan Minerals - AI Chatbot Knowledge Base & NLP Engine
 * Authoritative technical data, mineral specifications, logistics, and company intelligence.
 */

export const COMPANY_INFO = {
  name: 'Balochistan Minerals (Pvt) Ltd',
  registration: 'SECP Registered (Private Limited)',
  email: 'sales@balochistanminerals.com',
  phone: '+92 334 8888104',
  whatsappNumber: '923348888104',
  headOffice: 'Karachi, Sindh, Pakistan (Commercial & Export Logistics Desk)',
  regionalOffice: 'Quetta, Balochistan, Pakistan (Mining Operations & Regional Supply Hub)',
  exportPorts: ['Port of Karachi (KPT)', 'Port Muhammad Bin Qasim (PQA)', 'Gwadar Deep Sea Port'],
  incoterms: ['FOB (Free on Board Karachi / Qasim)', 'CFR (Cost and Freight)', 'CIF (Cost, Insurance & Freight)', 'EXW (Stockyard / Mine gate)'],
  inspectionAgencies: ['SGS Pakistan', 'Alfred H Knight (AHK)', 'Inspectorate / Bureau Veritas', 'Buyer Nominated Surveyors'],
  activeMiningRegions: [
    { name: 'Muslim Bagh & Khanozai', mineral: 'Chromite Ore & Concentrate' },
    { name: 'Khuzdar District', mineral: 'High Specific Gravity Barite (Baryte)' },
    { name: 'Chagai District', mineral: 'Copper-Gold Metallogenic Belt (Porphyry/Lump Ore)' },
    { name: 'Qila Abdullah', mineral: 'Antimony (Stibnite)' },
    { name: 'Sorange-Degari & Mach', mineral: 'Sub-Bituminous Industrial Coal' }
  ]
};

export const MINERAL_DATABASE = {
  barite: {
    id: 'barite',
    name: 'Barite / Baryte (Barium Sulfate)',
    formula: 'BaSO4',
    category: 'Industrial Minerals',
    overview: 'High-density mineral primarily supplied as weighting material for oil & gas drilling fluids (API Spec 13A compliant), as well as filler grades for paints, plastics, brake linings, and chemical compounds.',
    grades: [
      { grade: 'API Drilling Grade', spec: 'Specific Gravity 4.20+ g/cm³ (API 13A), BaSO4 > 90%, low soluble alkaline earth metals (< 250 ppm Ca).' },
      { grade: 'Heavy Drilling Grade', spec: 'Specific Gravity 4.10 - 4.19 g/cm³.' },
      { grade: 'High-Purity Chemical Grade', spec: '92% - 98% BaSO4, controlled whiteness, low Fe2O3 (< 0.2%).' }
    ],
    origin: 'Khuzdar district, Balochistan, Pakistan.',
    forms: 'Lumps (0-100mm, 10-50mm), unground crude, or 200/325 mesh milled powder.',
    packaging: 'Bulk vessel loading, or 1.0 MT / 1.5 MT heavy-duty jumbo bags with PE liner.',
    buyerChecklist: ['Specific gravity (SG) pycnometer test', 'Water-soluble alkaline earths as calcium', 'Residue on 75-micron (200 mesh) & 45-micron screens', 'Moisture content (< 1%)'],
    url: 'product-industrial.html#barite'
  },
  chromite: {
    id: 'chromite',
    name: 'Chromite Ore & Concentrate',
    formula: 'FeCr2O4',
    category: 'Metallic Minerals',
    overview: 'High-grade chrome ore sourced from the famous Muslim Bagh ophiolite belt. Essential for ferrochrome production, stainless steel alloys, metallurgical foundries, and refractory materials.',
    grades: [
      { grade: 'High Grade Metallurgical Lumps', spec: '42% - 52% Cr2O3, Cr:Fe ratio 2.6:1 to 3.2:1.' },
      { grade: 'Medium / Foundry Grade Lumps', spec: '32% - 40% Cr2O3, Cr:Fe ratio 2.2:1 to 2.6:1.' },
      { grade: 'Refractory / Low Grade Ore', spec: '12% - 30% Cr2O3.' },
      { grade: 'Chromite Sand Concentrate', spec: '38% - 52% Cr2O3 finely washed spiral concentrate.' }
    ],
    origin: 'Muslim Bagh, Khanozai, and Zhob valley, Balochistan, Pakistan.',
    forms: 'Run-of-mine (ROM) lumps (10-300mm), screened lumps (10-50mm), or fines/concentrate.',
    packaging: 'Containerized (20ft FCL ~27-28 MT), 1.25 MT big bags, or break-bulk vessel loads.',
    buyerChecklist: ['Cr2O3 content (XRF/wet chemical assay)', 'Cr:Fe ratio', 'Silica (SiO2 < 6-8%)', 'Phosphorus (P < 0.007%) & Sulfur', 'Moisture (< 2%)'],
    url: 'product-metallic.html#chromite'
  },
  copper: {
    id: 'copper',
    name: 'Copper Ore & Direct Shipping Ore (DSO)',
    formula: 'Cu',
    category: 'Metallic Minerals',
    overview: 'High-potential copper ore extracted from the Tethyan Metallogenic Belt in Chagai (along the Reko Diq and Saindak regional trends). Supplied as high-grade Direct Shipping Ore (DSO) for smelters and international trading programs.',
    grades: [
      { grade: 'Direct Shipping Ore (DSO)', spec: '3% - 10%+ Cu lumps (malachite, chrysocolla, azurite, chalcopyrite).' },
      { grade: 'Medium Grade Sourcing', spec: '2% - 5% Cu lumps with consistent lot composite assays.' },
      { grade: 'Concentrate Potential', spec: 'Assay-based lots with low arsenic and controlled bismuth.' }
    ],
    origin: 'Chagai district, Balochistan, Pakistan.',
    forms: 'Crude lumps, sized run-of-mine, hand-sorted high-grade lots.',
    packaging: 'Heavy-duty 1.0 MT / 1.5 MT big bags loaded in 20ft dry containers (~26-28 MT per container) or break-bulk.',
    buyerChecklist: ['Cu total assay & acid-soluble copper', 'Silica (SiO2) & iron (Fe) content', 'Deleterious penalty elements (As, Sb, Bi, Pb, Hg)', 'Moisture percentage'],
    url: 'product-metallic.html#copper'
  },
  ironOre: {
    id: 'iron-ore',
    name: 'Iron Ore (Magnetite & Hematite)',
    formula: 'Fe / Fe2O3 / Fe3O4',
    category: 'Metallic Minerals',
    overview: 'Export-grade iron ore supplied to steel mills, pelletizing plants, and cement works. Both lump ore and milled concentrate available with low gangue and minimal deleterious elements.',
    grades: [
      { grade: 'Direct Reduction / Steel Mill Lumps', spec: '50% - 58% Fe lumps.' },
      { grade: 'Medium Sinter Feed / Cement Grade', spec: '40% - 48% Fe.' },
      { grade: 'Magnetite Beneficiated Concentrate', spec: '56% - 62%+ Fe with low silica and phosphorus.' }
    ],
    origin: 'Balochistan deposits with rail and road linkages to Karachi ports.',
    forms: 'Lumps (10-40mm), fines (0-10mm), or beneficiated concentrate.',
    packaging: 'Break-bulk vessel shipments (15,000 - 45,000 MT) or containerized jumbo bags.',
    buyerChecklist: ['Total Fe and FeO content', 'Alumina (Al2O3) & Silica (SiO2) ratio', 'Phosphorus (P < 0.05%) and Sulfur (S < 0.05%)', 'Physical shatter index & sizing'],
    url: 'product-metallic.html#iron-ore'
  },
  fluorite: {
    id: 'fluorite',
    name: 'Fluorite / Fluorspar (Calcium Fluoride)',
    formula: 'CaF2',
    category: 'Industrial Minerals',
    overview: 'Supplied for steel fluxing, ceramic glazes, glass manufacturing, and hydrofluoric acid chemical manufacturing.',
    grades: [
      { grade: 'Metallurgical Grade (Metspar)', spec: '60% - 85% CaF2, low sulfur, sized lumps for electric arc furnaces.' },
      { grade: 'High Grade / Acid Grade Precursor', spec: '85% - 92%+ CaF2.' },
      { grade: 'Ceramic Grade', spec: 'Controlled color, low iron oxide (Fe2O3 < 0.12%).' }
    ],
    origin: 'Kalat and surrounding mineral districts, Balochistan.',
    forms: 'Lumps (10-60mm), crushed gravel, or milled fine powder.',
    packaging: 'Jumbo bags (1.0 MT) or containerized bulk.',
    buyerChecklist: ['CaF2 purity', 'SiO2 silica penalty', 'CaCO3 carbonate level', 'Phosphorus and sulfur'],
    url: 'product-industrial.html#fluorite'
  },
  antimony: {
    id: 'antimony',
    name: 'Antimony Ore (Stibnite)',
    formula: 'Sb2S3',
    category: 'Metallic Minerals',
    overview: 'High-density antimony sulfide ore used in flame retardants, lead-acid batteries, solar cell glass clarification, and strategic alloys.',
    grades: [
      { grade: 'High Grade Stibnite Lumps', spec: '25% - 58% Sb.' },
      { grade: 'Crude Ore / Low Grade', spec: '4% - 20% Sb.' },
      { grade: 'Antimony Concentrate', spec: '20% - 60% Sb by flotation/gravity.' }
    ],
    origin: 'Qila Abdullah and western Balochistan belts.',
    forms: 'Hand-picked crystalline lumps or concentrated crushed lots.',
    packaging: 'Durable 1 MT jumbo bags in sealed shipping containers with tamper seals.',
    buyerChecklist: ['Sb assay percentage', 'Lead (Pb), Arsenic (As), and Bismuth (Bi) limits', 'Moisture content'],
    url: 'product-metallic.html#antimony'
  },
  gypsum: {
    id: 'gypsum',
    name: 'Gypsum (Hydrated Calcium Sulfate)',
    formula: 'CaSO4·2H2O',
    category: 'Industrial Minerals',
    overview: 'High-purity natural gypsum required as setting retarder in Portland cement production, drywall plasterboards, agriculture soil amendment, and building plasters.',
    grades: [
      { grade: 'Cement & Plaster Grade', spec: '90% - 95% CaSO4·2H2O purity, high brightness, low moisture.' },
      { grade: 'Agricultural Grade', spec: '80% - 88% purity with high calcium and sulfate soil availability.' }
    ],
    origin: 'Extensive mineral beds in Balochistan.',
    forms: 'Run-of-mine lumps (0-150mm), crushed (0-50mm), or screened cement rock.',
    packaging: 'Break-bulk vessel charter or containerized.',
    buyerChecklist: ['Combined water of hydration', 'Purity percentage', 'Free moisture (< 1.5%)', 'Chlorides (< 0.02%)'],
    url: 'product-industrial.html#gypsum'
  },
  magnesite: {
    id: 'magnesite',
    name: 'Magnesite (Magnesium Carbonate)',
    formula: 'MgCO3',
    category: 'Industrial Minerals',
    overview: 'Sourced for refractory brick manufacturing, steel converters, caustic calcined magnesia (CCM), and agricultural animal feed formulations.',
    grades: [
      { grade: 'Raw Magnesite Ore', spec: '42% - 47% raw MgO (equivalent to 88% - 96% MgCO3).' },
      { grade: 'Calcined Options', spec: '85% - 90% reactive or dead-burned MgO.' }
    ],
    origin: 'Balochistan ophiolite contact zones (Muslim Bagh / Khuzdar).',
    forms: 'Dense white/grey lumps or calcined briquettes.',
    packaging: '1.0 MT jumbo bags or loose bulk in 20ft containers.',
    buyerChecklist: ['MgO percentage', 'CaO lime contamination (< 1.5%)', 'SiO2 silica (< 2.5%)', 'Loss on Ignition (LOI ~50% in raw)'],
    url: 'product-industrial.html#magnesite'
  },
  phosphate: {
    id: 'phosphate-rock',
    name: 'Phosphate Rock',
    formula: 'P2O5 Feedstock',
    category: 'Industrial Minerals',
    overview: 'Feedstock for single superphosphate (SSP), phosphoric acid, direct soil application, and mineral fertilizer blending.',
    grades: [
      { grade: 'Fertilizer Grade', spec: 'P2O5 assay based per lot (typically 22% - 30% P2O5).' }
    ],
    origin: 'Sedimentary formations in Balochistan.',
    forms: 'Lumps, crushed aggregate, or screened ground rock.',
    packaging: 'Bulk containerized or bulk vessel.',
    buyerChecklist: ['P2O5 content', 'CaO:P2O5 ratio', 'MER (Minor Element Ratio: Fe2O3+Al2O3+MgO / P2O5)', 'Carbonate (CO2)'],
    url: 'product-industrial.html#phosphate-rock'
  },
  bauxite: {
    id: 'bauxite',
    name: 'Bauxite Ore',
    formula: 'Al2O3·2H2O',
    category: 'Industrial Minerals',
    overview: 'Alumina-rich ore suited for refractory cement, abrasive manufacturing, metallurgical alumina refining, and steel deoxidation.',
    grades: [
      { grade: 'Refractory / Alumina Grade', spec: '45% - 62% Al2O3, low reactive silica.' }
    ],
    origin: 'Balochistan mineral belts.',
    forms: 'Crude lumps or crushed screened ore.',
    packaging: 'Bulk containerized or jumbo bags.',
    buyerChecklist: ['Total Al2O3 and available alumina', 'Reactive silica (SiO2)', 'Fe2O3 and TiO2 levels'],
    url: 'product-industrial.html#bauxite'
  },
  stones: {
    id: 'stones',
    name: 'Dimensional Stones & Marble',
    formula: 'Natural Stone',
    category: 'Dimensional Stones',
    overview: 'Quarried dimensional stones including Persian Silk Tundra Grey, Pietra Grey marble, White Marble, and Onyx for luxury architectural slabs, tiles, and block export.',
    grades: [
      { grade: 'Persian Silk / Tundra Grey', spec: 'Uniform soft-grey background with subtle white calcite veining. Gangsaw blocks and 20mm/30mm polished slabs.' },
      { grade: 'Pietra Grey', spec: 'Deep charcoal-grey marble with fine white quartz striations. High polish retention.' },
      { grade: 'Balochistan Onyx & White Marble', spec: 'Translucent green onyx and high-density crystalline white marble.' }
    ],
    origin: 'Quarries across Balochistan and regional processing corridors.',
    forms: 'Quarry blocks (wire-saw squared), gangsaw slabs (20mm, 30mm), cut-to-size tiles.',
    packaging: 'Reinforced wooden bundles (A-frames) for slabs; sea-fastened blocks in heavy-duty 20ft containers.',
    buyerChecklist: ['Block dimensions & squareness', 'Crack-free sonic inspection', 'Polished surface gloss (> 90)', 'Uniform color tone'],
    url: 'product-stones.html'
  },
  coal: {
    id: 'coal',
    name: 'Coal (Sub-Bituminous Industrial Coal)',
    formula: 'Carbonaceous',
    category: 'Energy Minerals',
    overview: 'High calorific industrial coal from Balochistan basins, serving cement clinker kilns, brick kilns, and industrial steam boilers.',
    grades: [
      { grade: 'Industrial Steam Coal', spec: 'Gross Calorific Value (GCV) 5,000 - 6,800 kcal/kg, low to moderate ash.' }
    ],
    origin: 'Sorange-Degari and Mach-Anjira coalfields, Balochistan.',
    forms: 'ROM lumps (0-200mm), screened coal (20-80mm), or fines.',
    packaging: 'Dump trucks for domestic delivery, bulk rakes, or Karachi port stockpile.',
    buyerChecklist: ['Calorific Value (GCV/NCV kcal/kg)', 'Total moisture and inherent moisture', 'Ash content', 'Volatile matter and sulfur percentage'],
    url: 'product-energy.html'
  }
};

export const FREQUENT_TOPICS = [
  {
    topic: 'rfq_pricing',
    keywords: ['price', 'pricing', 'quote', 'cost', 'rfq', 'rate', 'quotation', 'inquiry', 'buy', 'order', 'purchase', 'per ton', 'how much'],
    title: 'Commercial Quotations & RFQ Process',
    answer: `Balochistan Minerals provides customized commercial quotations based on exact lot assays, tonnage, and chosen Incoterm (FOB Karachi, CFR, or CIF destination port).

**Standard Commercial Terms:**
• **Payment Terms:** Irrevocable Letter of Credit (L/C at sight from prime international bank) or Telegraphic Transfer (T/T deposit + balance against shipping documents).
• **Assay / Inspection:** Pre-shipment independent inspection by SGS, Alfred H Knight (AHK), or buyer-nominated surveyor.
• **Minimum Order Quantity (MOQ):** Containerized trial shipments start from 1 FCL (~26-28 MT). Break-bulk vessel shipments start from 5,000 to 45,000 MT.

*Would you like to generate an official Request for Quotation (RFQ) right now? Use the quick form below or contact our sales desk directly.*`
  },
  {
    topic: 'logistics_shipping',
    keywords: ['logistics', 'shipping', 'port', 'karachi', 'qasim', 'gwadar', 'vessel', 'container', 'fob', 'cif', 'cfr', 'delivery', 'transport', 'incoterm', 'freight'],
    title: 'Mine-to-Port Logistics & Export Capabilities',
    answer: `We operate integrated logistics from Balochistan mine stockyards directly to Pakistan export gateways:

• **Export Ports:**
  1. **Port of Karachi (KPT):** Primary containerized terminal and break-bulk berth.
  2. **Port Muhammad Bin Qasim (PQA):** Specialized industrial terminal with deep draft and bulk loading equipment.
  3. **Gwadar Deep Sea Port:** Strategic western gateway for regional shipments.
• **Shipping Modes:**
  • **20ft Dry Cargo Containers:** Loaded with 1.0 MT - 1.5 MT heavy-duty jumbo bags (Payload: ~26-28 MT/FCL).
  • **Break-Bulk Charter:** Supramax / Handymax bulk vessels for iron ore, chromite, and gypsum (10,000 - 45,000 MT).
• **Incoterms Supported:** FOB Karachi/Qasim, CFR, CIF worldwide ports (China, GCC, Far East, Europe, Americas).`
  },
  {
    topic: 'quality_assay',
    keywords: ['assay', 'coa', 'quality', 'sgs', 'inspection', 'lab', 'certificate', 'analysis', 'purity', 'ahk', 'testing', 'report'],
    title: 'Quality Assurance & Assay Certification',
    answer: `Every mineral lot from Balochistan Minerals is tested and documented before export:

• **Independent Assay Reports:** We work with **SGS Pakistan**, **Alfred H Knight (AHK)**, and **Inspectorate** to draw composite stockpile samples.
• **Export Documentation Provided:**
  • Certificate of Analysis (COA) specifying grade, moisture, and trace elements.
  • Certificate of Weight and Packing List.
  • Certificate of Origin (Chamber of Commerce / Trade Development Authority of Pakistan).
  • Commercial Invoice & Clean on Board Bill of Lading (B/L).
• **Pre-shipment Inspection:** Buyers may dispatch their own third-party surveyors to witness sampling at mine-site or port stockyards prior to loading.`
  },
  {
    topic: 'samples',
    keywords: ['sample', 'specimen', 'trial', 'testing sample', 'courier', 'dhl'],
    title: 'Mineral Sampling & Laboratory Specimens',
    answer: `We provide genuine, representative mineral specimens and pulverized lab samples for qualified international buyers and smelting engineers:

• **Courier Dispatch:** 1 kg to 5 kg sealed samples dispatched via DHL / FedEx with preliminary lab assay certificate.
• **Sample Requirements:** Please provide your company details, target technical specifications, and delivery address.
• **Trial Shipments:** We also support 1-2 container trial orders (26 - 54 MT) so your processing facility can test run-of-kiln or furnace yield.`
  },
  {
    topic: 'mines_locations',
    keywords: ['mine', 'mines', 'origin', 'where', 'location', 'muslim bagh', 'khuzdar', 'chagai', 'quetta', 'karachi', 'balochistan'],
    title: 'Our Mine Operations & Belts in Balochistan',
    answer: `Our operations and sourcing networks span Balochistan’s richest geological belts:

• **Muslim Bagh & Khanozai (Chromite):** High Cr2O3 metallurgical chrome lumps and spiral sand concentrate.
• **Khuzdar District (Barite):** Premium high-gravity API drilling barite (SG 4.20+ g/cm³) and chemical grades.
• **Chagai Metallogenic Arc (Copper):** Direct shipping copper ore (2%-10% Cu) along the prolific Tethyan belt.
• **Qila Abdullah (Antimony):** High-purity Stibnite crystal lumps.
• **Central Balochistan (Stone & Marble):** Persian Silk, Pietra Grey, and Onyx quarries.

All extraction operates with environmental oversight, local tribal community partnerships, and SECP corporate governance.`
  },
  {
    topic: 'contact_office',
    keywords: ['contact', 'office', 'phone', 'email', 'address', 'whatsapp', 'reach', 'talk', 'human', 'representative', 'sales'],
    title: 'Direct Office & Executive Contact',
    answer: `You can reach our commercial trading team directly:

• **Commercial Email:** [sales@balochistanminerals.com](mailto:sales@balochistanminerals.com)
• **Phone & WhatsApp:** [+92 334 8888104](https://wa.me/923348888104)
• **Head Office (Karachi):** Export documentation, trade finance, and shipping logistics desk.
• **Regional Hub (Quetta):** Mine dispatch, site inspection, and regional assay coordination.
• **Response Time:** Commercial inquiries are reviewed within 2 to 4 business hours.`
  }
];

/**
 * Intelligent Intent Classifier & Matcher
 * Analyzes natural language input and finds the highest-confidence domain match.
 */
export function findBestAnswer(query) {
  const clean = (query || '').toLowerCase().trim();
  if (!clean) {
    return {
      type: 'greeting',
      title: 'How can I assist your mineral sourcing today?',
      text: 'I can assist you with technical assay specifications, current mine production, logistics from Karachi/Gwadar ports, or generate an instant commercial RFQ.',
      chips: ['Barite 4.2+ SG Specs', 'Chromite Ore Grades', 'Chagai Copper Ore', 'Mine-to-Port Logistics', 'Request a Quote (RFQ)']
    };
  }

  // 1. Direct RFQ / Quote Intent
  const rfqTerms = ['quote', 'rfq', 'price', 'pricing', 'cost', 'buy', 'order', 'quotation', 'rate', 'how much', 'tonnage'];
  const hasRfqIntent = rfqTerms.some(term => clean.includes(term));

  // Check if a specific mineral is mentioned alongside RFQ intent
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
          title: `Commercial Quotation for ${mineral.name}`,
          text: `To prepare an official quotation for **${mineral.name}**, our commercial desk requires your target tonnage, destination port, and desired assay grade.`,
          showRfqCard: true,
          rfqData: {
            mineralName: mineral.name,
            defaultGrade: mineral.grades[0]?.grade || 'Export Grade',
            url: mineral.url
          },
          chips: [`${mineral.name} Specs`, 'Shipping Terms (FOB/CIF)', 'Inspection & SGS', 'Contact Sales Desk']
        };
      }

      // Return comprehensive mineral technical overview
      let gradesSummary = mineral.grades.map(g => `• **${g.grade}:** ${g.spec}`).join('\n');
      let checklistSummary = mineral.buyerChecklist.map(c => `• ${c}`).join('\n');

      return {
        type: 'mineral_detail',
        mineral: mineral,
        title: `${mineral.name} (${mineral.formula})`,
        text: `### ${mineral.name} — Technical Export Overview
*Category: ${mineral.category} | Origin: ${mineral.origin}*

${mineral.overview}

**Export Specifications & Available Grades:**
${gradesSummary}

**Supply & Packaging Details:**
• **Available Forms:** ${mineral.forms}
• **Export Packing:** ${mineral.packaging}

**Buyer Verification Checklist:**
${checklistSummary}`,
        url: mineral.url,
        showRfqCard: true,
        rfqData: {
          mineralName: mineral.name,
          defaultGrade: mineral.grades[0]?.grade || 'Standard Lot',
          url: mineral.url
        },
        chips: [`Request ${mineral.name.split(' ')[0]} Quote`, 'Mine-to-Port Logistics', 'Assay Certificate Details', 'Order Lab Sample']
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
        showRfqCard: topic.topic === 'rfq_pricing',
        chips: topic.topic === 'rfq_pricing'
          ? ['Barite 4.2+ SG', 'Chromite 42-52%', 'Chagai Copper', 'Talk to Sales Specialist']
          : ['Request a Quote (RFQ)', 'Logistics & Ports', 'Assay Quality Guarantee', 'All Mineral Products']
      };
    }
  }

  // 3. Fallback Smart Response
  return {
    type: 'fallback',
    title: 'Balochistan Minerals AI Assistant',
    text: `I'm happy to help you with our Pakistan mineral operations. I specialize in:

• **Export Minerals:** Barite (4.2+ SG), Chromite (Muslim Bagh), Copper (Chagai), Iron Ore, Fluorite, Gypsum, Antimony, and Persian Silk / Pietra Grey Marble.
• **Supply Chain:** Stockyard logistics, containerized & break-bulk vessel loading from Port of Karachi and Port Qasim.
• **Commercial Desk:** SGS assay verification, incoterms (FOB, CFR, CIF), and custom RFQ pricing.

*You can click one of the quick options below or specify which mineral or technical parameter you need:*`,
    chips: [
      'Barite API 4.2+ Specs',
      'Chromite 42-52% Ore',
      'Chagai Copper Ore',
      'Mine-to-Port Logistics',
      'Request Official Quote (RFQ)',
      'Contact Sales Desk'
    ]
  };
}

/**
 * Generates pre-filled WhatsApp link with professional RFQ text
 */
export function buildWhatsAppRfqUrl({ mineral, quantity, destinationPort, contactName, company }) {
  const text = `*BALOCHISTAN MINERALS - B2B COMMERCIAL INQUIRY (RFQ)*
--------------------------------------
• *Mineral:* ${mineral || 'Mineral Sourcing'}
• *Quantity:* ${quantity || 'Trial / Multi-Container'}
• *Destination Port:* ${destinationPort || 'FOB Karachi / CIF'}
• *Buyer Name:* ${contactName || 'Trade Buyer'}
• *Company:* ${company || 'International Buyer'}
--------------------------------------
Sent via Balochistan Minerals AI Portal. Please advise current FOB/CIF pricing and assay certificate.`;

  return `https://wa.me/${COMPANY_INFO.whatsappNumber}?text=${encodeURIComponent(text)}`;
}

/**
 * Generates pre-filled mailto link with structured RFQ details
 */
export function buildEmailRfqUrl({ mineral, quantity, destinationPort, contactName, company }) {
  const subject = `[RFQ] Commercial Inquiry for ${mineral || 'Mineral Supply'} - ${company || 'International Buyer'}`;
  const body = `Dear Balochistan Minerals Commercial Team,

I am requesting an official quotation and specification sheet for:

- Mineral: ${mineral || 'Export Minerals'}
- Target Quantity: ${quantity || 'To be specified'}
- Destination Port / Incoterm: ${destinationPort || 'FOB Karachi / CIF'}
- Buyer / Company: ${contactName || 'Representative'} (${company || 'Company'})

Please provide current assay data (COA), availability schedule, and commercial pricing.

Kind regards,
${contactName || 'Buyer'}
${company || ''}`;

  return `mailto:${COMPANY_INFO.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}
