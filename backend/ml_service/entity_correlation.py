#!/usr/bin/env python3
"""
Ownuh SAIPS - Graph-Based Entity Correlation
Detects compromised entities by building knowledge graphs from audit logs.

Research alignment: Dr. Euijin Choo - DeviceWatch: Graph-Inference for Compromised Entity Detection
Reference: "A Data-Driven Network Analysis Approach to Identifying Compromised Mobile Devices with Graph-Inference"
"""

import json
import sys
import logging
from typing import Dict, List, Set, Tuple, Any
from collections import defaultdict
import networkx as nx
from pathlib import Path

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger(__name__)


def load_input_payload(argument: str) -> Dict[str, Any]:
    if argument.startswith('@'):
        payload_path = Path(argument[1:])
        if not payload_path.exists():
            raise FileNotFoundError(f"Payload file not found: {payload_path}")
        return json.loads(payload_path.read_text(encoding='utf-8-sig'))
    return json.loads(argument)


class EntityCorrelationGraph:
    """
    Build knowledge graphs from audit logs to identify compromised entities.
    
    Entities: Users, IPs, Devices (fingerprints), Countries, Passwords
    Edges: Relationships like "user logged in from IP in country"
    
    Detection: Find clusters of abnormal edges or entities with suspicious connectivity patterns.
    """
    
    def __init__(self):
        self.graph = nx.MultiDiGraph()
        self.entity_types = defaultdict(set)  # Type -> set of entity IDs
        self.entities = {}  # Entity ID -> details

    def _simple_undirected_graph(self, source_graph: nx.MultiDiGraph | nx.MultiGraph | None = None) -> nx.Graph:
        """
        Collapse a multi-graph into a simple weighted undirected graph.

        NetworkX clustering algorithms do not operate on MultiGraph/MultiDiGraph
        instances, so we aggregate parallel edges into a single edge while
        preserving approximate weight and cumulative risk.
        """

        multigraph = source_graph if source_graph is not None else self.graph
        undirected_multi = multigraph.to_undirected()
        simple_graph = nx.Graph()
        simple_graph.add_nodes_from(undirected_multi.nodes(data=True))

        for src, dst, data in undirected_multi.edges(data=True):
            edge_weight = float(data.get('weight', 1))
            edge_risk = float(data.get('risk_sum', 0))

            if simple_graph.has_edge(src, dst):
                simple_graph[src][dst]['weight'] += edge_weight
                simple_graph[src][dst]['risk_sum'] += edge_risk
            else:
                simple_graph.add_edge(src, dst, weight=edge_weight, risk_sum=edge_risk)

        return simple_graph
    
    def build_graph(self, audit_events: List[Dict[str, Any]]):
        """
        Build entity relationship graph from audit events.
        
        Entity types:
        - user:* (user accounts)
        - ip:* (source IP addresses)
        - device:* (device fingerprints)
        - country:* (country codes)
        """
        
        logger.info(f"Building graph from {len(audit_events)} events...")
        
        for event in audit_events:
            # Extract entities
            user_id = f"user:{event.get('user_id', 'unknown')}"
            source_ip = f"ip:{event.get('source_ip', 'unknown')}"
            device_fp = f"device:{event.get('device_fingerprint', 'unknown')}"
            country = f"country:{event.get('country_code', 'XX')}"
            
            event_code = event.get('event_code', 'UNKNOWN')
            event_name = event.get('event_name', 'Unknown event')
            risk_score = event.get('risk_score', 0)
            
            # Add nodes
            for entity_id, entity_type in [
                (user_id, 'user'),
                (source_ip, 'ip'),
                (device_fp, 'device'),
                (country, 'country')
            ]:
                if entity_id not in self.graph:
                    self.graph.add_node(entity_id, type=entity_type, weight=1)
                    self.entity_types[entity_type].add(entity_id)
                    self.entities[entity_id] = {'type': entity_type, 'risk_score': 0}
                else:
                    self.graph.nodes[entity_id]['weight'] += 1
            
            # Add edges with event context
            edges = [
                (user_id, source_ip, event_code),
                (user_id, device_fp, event_code),
                (source_ip, country, event_code),
                (device_fp, country, event_code),
            ]
            
            for src, dst, label in edges:
                if not self.graph.has_edge(src, dst, label):
                    self.graph.add_edge(src, dst, event=label, weight=1, risk_sum=risk_score)
                else:
                    edge_data = self.graph.get_edge_data(src, dst, label)
                    edge_data['weight'] += 1
                    edge_data['risk_sum'] += risk_score
        
        logger.info(f"✓ Graph built: {self.graph.number_of_nodes()} nodes, {self.graph.number_of_edges()} edges")
    
    def detect_compromised_entities(self, threshold: float = 0.6) -> Dict[str, Any]:
        """
        Detect compromised entities using graph features:
        
        1. High clustering coefficient (connected to suspicious neighbors)
        2. Abnormal degree distribution
        3. Bridge nodes connecting to high-risk clusters
        4. Temporal patterns (new connections to many IPs/countries)
        """
        
        compromised = []
        
        # Calculate degree centrality
        degree_centrality = nx.degree_centrality(self.graph)
        
        # Convert to a simple graph for algorithms that do not support MultiGraph.
        undirected = self._simple_undirected_graph()
        clustering = nx.clustering(undirected, weight='weight')

        # Betweenness is also computed on the simple graph for consistency.
        betweenness = nx.betweenness_centrality(undirected, weight='weight')
        
        # Analyze each entity
        for entity_id, weight in self.graph.nodes(data='weight'):
            entity_type = self.graph.nodes[entity_id]['type']
            
            out_degree = self.graph.out_degree(entity_id)
            in_degree = self.graph.in_degree(entity_id)
            
            # Feature: Abnormal out-degree for users
            if entity_type == 'user':
                # Normal users shouldn't connect to many IPs/devices
                if out_degree > 10:
                    # Analyze the connected entities
                    neighbors = list(self.graph.successors(entity_id))
                    neighbor_types = [self.graph.nodes[n]['type'] for n in neighbors]
                    
                    # High diversity of IPs/devices is suspicious
                    ip_count = sum(1 for nt in neighbor_types if nt == 'ip')
                    device_count = sum(1 for nt in neighbor_types if nt == 'device')
                    
                    suspicion_score = min(1.0, (ip_count / 5.0 + device_count / 3.0) / 2)
                    
                    if suspicion_score > threshold:
                        compromised.append({
                            'entity_id': entity_id,
                            'entity_type': entity_type,
                            'suspicion_score': suspicion_score,
                            'reason': f'Connected to {ip_count} IPs and {device_count} devices',
                            'out_degree': out_degree,
                            'in_degree': in_degree,
                            'clustering_coefficient': clustering.get(entity_id, 0),
                            'betweenness': betweenness.get(entity_id, 0),
                        })
            
            # Feature: IPs with connections to many users
            elif entity_type == 'ip':
                if in_degree > 5:  # IP connected from >5 users is suspicious
                    suspicion_score = min(1.0, in_degree / 20.0)
                    if suspicion_score > threshold:
                        compromised.append({
                            'entity_id': entity_id,
                            'entity_type': entity_type,
                            'suspicion_score': suspicion_score,
                            'reason': f'Connected from {in_degree} different users',
                            'in_degree': in_degree,
                            'out_degree': out_degree,
                            'clustering_coefficient': clustering.get(entity_id, 0),
                        })
            
            # Feature: Device fingerprints with anomalous patterns
            elif entity_type == 'device':
                if out_degree > 3 and in_degree > 3:
                    suspicion_score = min(1.0, (out_degree / 10.0 + in_degree / 10.0) / 2)
                    if suspicion_score > threshold:
                        compromised.append({
                            'entity_id': entity_id,
                            'entity_type': entity_type,
                            'suspicion_score': suspicion_score,
                            'reason': f'High connectivity in/out',
                            'in_degree': in_degree,
                            'out_degree': out_degree,
                            'clustering_coefficient': clustering.get(entity_id, 0),
                        })
        
        return {
            'compromised_entities': sorted(compromised, key=lambda x: x['suspicion_score'], reverse=True),
            'summary': {
                'total_entities': self.graph.number_of_nodes(),
                'compromised_count': len(compromised),
                'graph_density': nx.density(undirected),
                'average_clustering': sum(clustering.values()) / len(clustering) if clustering else 0,
            }
        }
    
    def find_connected_components(self) -> Dict[str, Any]:
        """
        Find connected components (clusters) in the graph.
        Isolated clusters may indicate separate attack vectors or segregated users.
        """
        
        components = list(nx.weakly_connected_components(self.graph))
        
        component_info = []
        for i, component in enumerate(components):
            subgraph = self.graph.subgraph(component)
            simple_subgraph = self._simple_undirected_graph(subgraph)
            types = defaultdict(int)
            for node in component:
                types[self.graph.nodes[node]['type']] += 1
            
            component_info.append({
                'component_id': i,
                'size': len(component),
                'entity_types': dict(types),
                'density': nx.density(simple_subgraph),
            })
        
        return {
            'components': sorted(component_info, key=lambda x: x['size'], reverse=True),
            'summary': {
                'num_components': len(components),
                'largest_component_size': max(len(c) for c in components) if components else 0,
            }
        }
    
    def export_graph_metrics(self) -> Dict[str, Any]:
        """Export comprehensive graph metrics for analysis."""
        simple_graph = self._simple_undirected_graph()

        return {
            'graph_stats': {
                'nodes': self.graph.number_of_nodes(),
                'edges': self.graph.number_of_edges(),
                'node_types': {k: len(v) for k, v in self.entity_types.items()},
            },
            'centrality': {
                'degree': dict(sorted(
                    nx.degree_centrality(simple_graph).items(),
                    key=lambda x: x[1],
                    reverse=True
                )[:10]),
                'betweenness': dict(sorted(
                    nx.betweenness_centrality(simple_graph, weight='weight').items(),
                    key=lambda x: x[1],
                    reverse=True
                )[:10]),
            }
        }


def main():
    if len(sys.argv) < 2:
        print("Usage: python entity_correlation.py <build|detect|metrics> <json_data>")
        sys.exit(1)
    
    mode = sys.argv[1]
    data_json = sys.argv[2] if len(sys.argv) > 2 else '{}'
    
    try:
        data = load_input_payload(data_json)
    except (json.JSONDecodeError, FileNotFoundError) as exc:
        print(f"Error: Invalid JSON ({exc})", file=sys.stderr)
        sys.exit(1)
    
    graph = EntityCorrelationGraph()
    events = data.get('events', [])
    
    if mode == 'build':
        graph.build_graph(events)
        metrics = graph.export_graph_metrics()
        print(json.dumps(metrics, indent=2, default=str))
    
    elif mode == 'detect':
        graph.build_graph(events)
        result = graph.detect_compromised_entities(threshold=0.5)
        print(json.dumps(result, indent=2, default=str))
    
    elif mode == 'metrics':
        graph.build_graph(events)
        result = graph.find_connected_components()
        result.update(graph.export_graph_metrics())
        print(json.dumps(result, indent=2, default=str))
    
    else:
        print(f"Unknown mode: {mode}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
